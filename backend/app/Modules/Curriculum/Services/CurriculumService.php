<?php

namespace App\Modules\Curriculum\Services;

use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Models\CurriculumCourse;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Curriculum\Models\ElectiveGroup;
use App\Modules\Identity\Models\User;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Curriculum lifecycle (PRD 8.1, 8.2): draft, review through the workflow engine, approval,
 * publication, retirement. Changes after submission need a new version.
 */
class CurriculumService
{
    public const PROCESS = 'curriculum_approval';

    public function __construct(private WorkflowEngine $workflows) {}

    public function createVersion(Programme $programme, array $data, User $by, ?CurriculumVersion $basedOn = null): CurriculumVersion
    {
        return DB::transaction(function () use ($programme, $data, $by, $basedOn) {
            Programme::whereKey($programme->id)->lockForUpdate()->first();
            $next = (int) CurriculumVersion::where('programme_id', $programme->id)->max('version') + 1;

            $version = CurriculumVersion::create($data + [
                'programme_id' => $programme->id,
                'version' => $next,
                'title' => $data['title'] ?? "{$programme->name} curriculum v{$next}",
                'duration_years' => $data['duration_years'] ?? $basedOn?->duration_years ?? $programme->duration_years,
                'required_credit_units' => $data['required_credit_units'] ?? $basedOn?->required_credit_units,
                'min_semester_units' => $data['min_semester_units'] ?? $basedOn?->min_semester_units,
                'max_semester_units' => $data['max_semester_units'] ?? $basedOn?->max_semester_units,
                'status' => 'draft',
                'based_on_id' => $basedOn?->id,
                'created_by' => $by->id,
            ]);

            if ($basedOn) {
                $groupMap = [];
                foreach ($basedOn->electiveGroups as $group) {
                    $groupMap[$group->id] = $version->electiveGroups()->create($group->only(['code', 'name', 'level', 'semester_sequence', 'min_units', 'max_units']))->id;
                }
                foreach ($basedOn->courses as $row) {
                    $version->courses()->create($row->only(['course_id', 'level', 'semester_sequence', 'category', 'credit_units']) + [
                        'elective_group_id' => $row->elective_group_id ? $groupMap[$row->elective_group_id] : null,
                    ]);
                }
            }

            return $version;
        });
    }

    public function updateVersion(CurriculumVersion $version, array $data): CurriculumVersion
    {
        $this->ensureEditable($version);
        $version->update($data);

        return $version;
    }

    public function addGroup(CurriculumVersion $version, array $data): ElectiveGroup
    {
        $this->ensureEditable($version);
        if ($version->electiveGroups()->where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages(['code' => 'This curriculum already has an elective group with that code.']);
        }

        return $version->electiveGroups()->create($data);
    }

    public function removeGroup(ElectiveGroup $group): void
    {
        $this->ensureEditable($group->version);
        if ($group->version->courses()->where('elective_group_id', $group->id)->exists()) {
            throw ValidationException::withMessages(['elective_group' => 'Move or remove the courses in this group first.']);
        }
        $group->delete();
    }

    public function addCourse(CurriculumVersion $version, Course $course, array $data): CurriculumCourse
    {
        $this->ensureEditable($version);
        if ($course->status !== 'active') {
            throw ValidationException::withMessages(['course_id' => "{$course->code} is retired and cannot be added."]);
        }
        if ($version->courses()->where('course_id', $course->id)->exists()) {
            throw ValidationException::withMessages(['course_id' => "{$course->code} is already in this curriculum."]);
        }
        $group = $this->group($version, $data['elective_group_id'] ?? null);

        return $version->courses()->create([
            'course_id' => $course->id,
            'level' => $data['level'] ?? $course->level,
            'semester_sequence' => $data['semester_sequence'] ?? $course->semester_sequence ?? 1,
            'category' => $data['category'],
            'credit_units' => $data['credit_units'] ?? $course->credit_units,
            'elective_group_id' => $group?->id,
        ]);
    }

    public function updateCourse(CurriculumCourse $row, array $data): CurriculumCourse
    {
        $this->ensureEditable($row->version);
        if (array_key_exists('elective_group_id', $data)) {
            $data['elective_group_id'] = $this->group($row->version, $data['elective_group_id'])?->id;
        }
        $row->update($data);

        return $row;
    }

    public function removeCourse(CurriculumCourse $row): void
    {
        $this->ensureEditable($row->version);
        $row->delete();
    }

    /** Checks a draft is complete enough to review, then starts the configured approval workflow. */
    public function submit(CurriculumVersion $version, User $by, ?string $remarks = null): WorkflowInstance
    {
        $this->ensureEditable($version);
        $version->load('programme.orgUnit', 'courses');

        $problems = [];
        if ($version->courses->isEmpty()) {
            $problems[] = 'Add at least one course.';
        }
        $total = $version->courses->sum('credit_units');
        if ($version->required_credit_units && $total < $version->required_credit_units) {
            $problems[] = "Mapped courses carry {$total} units but {$version->required_credit_units} are required.";
        }
        if ($problems) {
            throw ValidationException::withMessages(['curriculum' => $problems]);
        }

        $existing = WorkflowInstance::where('subject_type', $version->getMorphClass())->where('subject_id', $version->id)
            ->where('status', WorkflowInstance::RETURNED)->first();
        if ($existing) {
            return $this->workflows->act($existing, $by, 'resubmit', $remarks);
        }

        return $this->workflows->start(self::PROCESS, $version, $by, [
            'department' => $version->programme->orgUnit->code,
            'programme' => $version->programme->code,
        ], $remarks);
    }

    /** Publishing makes an approved version available to its entry cohorts. */
    public function publish(CurriculumVersion $version, User $by): CurriculumVersion
    {
        if ($version->status !== 'approved') {
            throw ValidationException::withMessages(['curriculum' => 'Only an approved curriculum can be published.']);
        }
        $version->forceFill(['status' => 'published', 'published_at' => now(), 'published_by' => $by->id])->save();

        return $version;
    }

    /** Retired versions stay attached to the students already on them; no new cohort can be placed on them. */
    public function retire(CurriculumVersion $version): CurriculumVersion
    {
        if ($version->status !== 'published') {
            throw ValidationException::withMessages(['curriculum' => 'Only a published curriculum can be retired.']);
        }
        $version->forceFill(['status' => 'retired', 'retired_at' => now()])->save();

        return $version;
    }

    /** Keeps the curriculum status in step with its approval workflow. */
    public function syncFromWorkflow(WorkflowInstance $instance): void
    {
        $version = CurriculumVersion::find($instance->subject_id);
        if (! $version) {
            return;
        }
        $status = match ($instance->status) {
            WorkflowInstance::IN_PROGRESS => 'in_review',
            WorkflowInstance::RETURNED => 'returned',
            WorkflowInstance::APPROVED => 'approved',
            WorkflowInstance::REJECTED => 'rejected',
            WorkflowInstance::CANCELLED => 'draft',
            default => $version->status,
        };
        if ($status !== $version->status) {
            $version->forceFill(['status' => $status, 'approved_at' => $status === 'approved' ? now() : $version->approved_at])->save();
        }
    }

    private function ensureEditable(CurriculumVersion $version): void
    {
        if (! $version->isEditable()) {
            throw ValidationException::withMessages(['curriculum' => "This curriculum is {$version->status}; create a new version to change it."]);
        }
    }

    private function group(CurriculumVersion $version, ?int $groupId): ?ElectiveGroup
    {
        if (! $groupId) {
            return null;
        }
        $group = $version->electiveGroups()->find($groupId);
        if (! $group) {
            throw ValidationException::withMessages(['elective_group_id' => 'That elective group is not part of this curriculum.']);
        }

        return $group;
    }
}
