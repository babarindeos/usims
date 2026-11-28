<?php

namespace App\Modules\Offerings\Services;

use App\Modules\Audit\Services\AuditContext;
use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Models\CurriculumCourse;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Institution\Models\Semester;
use App\Modules\Institution\Models\Setting;
use App\Modules\Offerings\Events\OfferingPublished;
use App\Modules\Offerings\Events\StaffAssigned;
use App\Modules\Offerings\Events\StaffAssignmentEnded;
use App\Modules\Offerings\Models\CourseOffering;
use App\Modules\Offerings\Models\CourseOfferingStaff;
use App\Modules\Staff\Models\Staff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferingService
{
    /** Default LMS short name, e.g. CSC301-2026-2027-1; institutions override it in the lms_shortname_format setting. */
    public const DEFAULT_SHORTNAME = '{code}-{session}-{semester}{group}';

    public const DEFAULT_TEACHING_CATEGORIES = ['academic'];

    public function __construct(private AccessService $access) {}

    public function create(Course $course, Semester $semester, array $data): CourseOffering
    {
        $this->ensureSemesterOpen($semester);
        $group = strtoupper($data['group_code'] ?? 'A');
        if (CourseOffering::where(['course_id' => $course->id, 'semester_id' => $semester->id, 'group_code' => $group])->exists()) {
            throw ValidationException::withMessages(['course_id' => "{$course->code} group {$group} is already offered this semester."]);
        }

        return CourseOffering::create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'group_code' => $group,
            'org_unit_id' => $data['org_unit_id'] ?? $course->org_unit_id,
            'capacity' => $data['capacity'] ?? null,
            'registration_opens_on' => $data['registration_opens_on'] ?? null,
            'registration_closes_on' => $data['registration_closes_on'] ?? null,
            'delivery_mode' => $data['delivery_mode'] ?? 'in_person',
            'status' => 'draft',
        ]);
    }

    /**
     * Draft offerings for every course the published curricula place in this semester, linked to the
     * programme cohorts that take them. Only courses whose department the user manages are created.
     *
     * @return array{created: int, linked: int, skipped_out_of_scope: int}
     */
    public function generateFromCurricula(Semester $semester, User $user): array
    {
        $this->ensureSemesterOpen($semester);
        $rows = CurriculumCourse::query()
            ->where('semester_sequence', $semester->sequence)
            ->whereHas('version', fn ($q) => $q->where('status', 'published'))
            ->whereHas('course', fn ($q) => $q->where('status', 'active'))
            ->with(['course.orgUnit', 'version'])
            ->get();

        $result = ['created' => 0, 'linked' => 0, 'skipped_out_of_scope' => 0];

        DB::transaction(function () use ($rows, $semester, $user, &$result) {
            foreach ($rows as $row) {
                if (! $this->access->allows($user, 'offerings.manage', $row->course)) {
                    $result['skipped_out_of_scope']++;

                    continue;
                }
                $offering = CourseOffering::firstOrCreate(
                    ['course_id' => $row->course_id, 'semester_id' => $semester->id, 'group_code' => 'A'],
                    ['org_unit_id' => $row->course->org_unit_id, 'status' => 'draft'],
                );
                $result['created'] += $offering->wasRecentlyCreated ? 1 : 0;

                $link = $offering->programmes()->firstOrCreate(['programme_id' => $row->version->programme_id, 'level' => $row->level]);
                $result['linked'] += $link->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $result;
    }

    public function publish(CourseOffering $offering, User $by): CourseOffering
    {
        if ($offering->status !== 'draft') {
            throw ValidationException::withMessages(['offering' => "This offering is {$offering->status}."]);
        }
        $offering->load('course', 'semester.session');
        $offering->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'published_by' => $by->id,
            'lms_shortname' => $offering->lms_shortname ?? $this->shortname($offering),
        ])->save();
        OfferingPublished::dispatch($offering);

        return $offering;
    }

    public function cancel(CourseOffering $offering, User $by, string $reason): CourseOffering
    {
        if (! in_array($offering->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['offering' => "This offering is {$offering->status}."]);
        }
        DB::transaction(function () use ($offering, $by, $reason) {
            app(AuditContext::class)->withReason($reason, fn () => $offering->update(['status' => 'cancelled']));
            $offering->staffAssignments()->where('status', 'active')->get()->each(fn ($a) => $this->endAssignment($a, $by, "Offering cancelled: {$reason}"));
        });

        return $offering;
    }

    /** Only active staff in an eligible category may hold teaching roles (PRD 9.4). */
    public function assignStaff(CourseOffering $offering, Staff $staff, string $role, User $by, ?string $reason = null): CourseOfferingStaff
    {
        if (in_array($offering->status, ['cancelled', 'closed'], true)) {
            throw ValidationException::withMessages(['offering' => "Staff cannot be assigned to a {$offering->status} offering."]);
        }
        if (! $staff->isActive()) {
            throw ValidationException::withMessages(['staff_id' => "This staff member is {$staff->employment_status} and cannot be assigned."]);
        }
        $eligible = Setting::where('key', 'teaching_eligible_categories')->value('value') ?? self::DEFAULT_TEACHING_CATEGORIES;
        if (! in_array($staff->category, $eligible, true)) {
            throw ValidationException::withMessages(['staff_id' => ucfirst($staff->category).' staff are not eligible for teaching roles under current policy.']);
        }
        if ($offering->staffAssignments()->where(['staff_id' => $staff->id, 'role' => $role, 'status' => 'active'])->exists()) {
            throw ValidationException::withMessages(['staff_id' => 'This person already holds that role on the offering.']);
        }

        $assignment = $offering->staffAssignments()->create([
            'staff_id' => $staff->id, 'role' => $role, 'effective_from' => now()->toDateString(),
            'status' => 'active', 'assigned_by' => $by->id, 'reason' => $reason,
        ]);
        StaffAssigned::dispatch($assignment);

        return $assignment;
    }

    public function endAssignment(CourseOfferingStaff $assignment, User $by, string $reason): CourseOfferingStaff
    {
        if ($assignment->status !== 'active') {
            throw ValidationException::withMessages(['assignment' => 'This assignment has already ended.']);
        }
        $assignment->update(['status' => 'ended', 'effective_to' => now()->toDateString(), 'ended_by' => $by->id, 'end_reason' => $reason]);
        StaffAssignmentEnded::dispatch($assignment);

        return $assignment;
    }

    /** Marks a departing staff member's live assignments for reassignment; they are not ended automatically. */
    public function flagAssignmentsOf(Staff $staff, string $reason): int
    {
        return CourseOfferingStaff::where('staff_id', $staff->id)->where('status', 'active')->whereNull('flagged_at')
            ->get()->each->update(['flagged_at' => now(), 'flag_reason' => $reason])->count();
    }

    public function clearFlagsOf(Staff $staff): void
    {
        CourseOfferingStaff::where('staff_id', $staff->id)->where('status', 'active')->whereNotNull('flagged_at')
            ->get()->each->update(['flagged_at' => null, 'flag_reason' => null]);
    }

    private function shortname(CourseOffering $offering): string
    {
        $format = Setting::where('key', 'lms_shortname_format')->value('value') ?? self::DEFAULT_SHORTNAME;

        return strtr($format, [
            '{code}' => str_replace(' ', '', $offering->course->code),
            '{session}' => str_replace('/', '-', $offering->semester->session->name),
            '{semester}' => (string) $offering->semester->sequence,
            '{group}' => $offering->group_code === 'A' ? '' : '-'.$offering->group_code,
        ]);
    }

    private function ensureSemesterOpen(Semester $semester): void
    {
        if ($semester->status === 'closed' || $semester->session?->isClosed()) {
            throw ValidationException::withMessages(['semester_id' => 'This semester is closed.']);
        }
    }
}
