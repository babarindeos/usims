<?php

namespace App\Modules\Offerings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\Uuid;
use App\Modules\Curriculum\Models\Course;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\Semester;
use App\Modules\Offerings\Http\Requests\OfferingRequest;
use App\Modules\Offerings\Http\Resources\OfferingResource;
use App\Modules\Offerings\Models\CourseOffering;
use App\Modules\Offerings\Models\CourseOfferingStaff;
use App\Modules\Offerings\Services\OfferingService;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Staff\Models\Staff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Published offerings are visible to every signed-in user; drafts to holders of offerings.view in the department. */
class OfferingController extends Controller
{
    private const DETAIL = ['course', 'semester.session', 'orgUnit', 'programmes.programme', 'staffAssignments.staff.person'];

    public function __construct(private OfferingService $offerings, private AccessService $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $visible = $this->access->restrictToOrgUnits($user, 'offerings.view', OrgUnit::query()->select('org_units.id'), 'org_units.id');

        $term = trim((string) $request->query('q'));
        $rows = CourseOffering::query()->with(['course', 'semester.session', 'orgUnit'])
            ->withCount(['staffAssignments as active_staff_count' => fn ($q) => $q->where('status', 'active'),
                'staffAssignments as flagged_staff_count' => fn ($q) => $q->where('status', 'active')->whereNotNull('flagged_at')])
            ->where(fn ($q) => $q->where('status', 'published')->orWhereIn('org_unit_id', $visible))
            ->when($request->query('semester'), fn ($q, $s) => $q->where('semester_id', (int) $s))
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas('orgUnit', fn ($w) => $w->where('uuid', $d)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('flagged'), fn ($q) => $q->whereHas('staffAssignments', fn ($w) => $w->where('status', 'active')->whereNotNull('flagged_at')))
            ->when($term !== '', fn ($q) => $q->whereHas('course', fn ($w) => $w->where('code', 'like', Course::normaliseCode($term).'%')
                ->orWhereRaw('lower(title) like ?', ['%'.mb_strtolower($term).'%'])))
            ->orderBy(Course::select('code')->whereColumn('courses.id', 'course_offerings.course_id'))->orderBy('group_code')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return OfferingResource::collection($rows);
    }

    public function show(CourseOffering $offering): OfferingResource
    {
        if ($offering->status !== 'published') {
            Gate::authorize('offerings.view', $offering);
        }

        return new OfferingResource($offering->load(self::DETAIL));
    }

    public function store(OfferingRequest $request): JsonResponse
    {
        $course = Uuid::find(Course::class, $request->validated('course_id'));
        $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id')) ?? $course->orgUnit;
        Gate::authorize('offerings.manage', $unit);

        $offering = $this->offerings->create($course, Semester::findOrFail($request->validated('semester_id')),
            $request->safe()->except(['course_id', 'semester_id', 'org_unit_id']) + ['org_unit_id' => $unit->id]);

        return (new OfferingResource($offering->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function generate(Request $request, Semester $semester): JsonResponse
    {
        // Scope is applied per course inside the service: out-of-scope courses are skipped and counted.
        abort_unless(in_array('offerings.manage', $this->access->permissionCodes($request->user()), true), 403);

        return response()->json(['data' => $this->offerings->generateFromCurricula($semester->load('session'), $request->user())]);
    }

    public function update(OfferingRequest $request, CourseOffering $offering): OfferingResource
    {
        Gate::authorize('offerings.manage', $offering);
        $this->ensureOpen($offering);
        $data = $request->safe()->except('org_unit_id');
        if ($request->has('org_unit_id')) {
            $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
            Gate::authorize('offerings.manage', $unit);
            $data['org_unit_id'] = $unit->id;
        }
        $offering->update($data);

        return new OfferingResource($offering->load(self::DETAIL));
    }

    public function publish(Request $request, CourseOffering $offering): OfferingResource
    {
        Gate::authorize('offerings.manage', $offering);

        return new OfferingResource($this->offerings->publish($offering, $request->user())->load(self::DETAIL));
    }

    public function cancel(Request $request, CourseOffering $offering): OfferingResource
    {
        Gate::authorize('offerings.manage', $offering);
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return new OfferingResource($this->offerings->cancel($offering, $request->user(), $request->input('reason'))->load(self::DETAIL));
    }

    public function addProgramme(Request $request, CourseOffering $offering): JsonResponse
    {
        Gate::authorize('offerings.manage', $offering);
        $this->ensureOpen($offering);
        $data = $request->validate([
            'programme_id' => ['required', 'string', 'exists:programmes,uuid'],
            'level' => ['required', 'integer', 'between:100,900', 'multiple_of:100'],
        ]);
        $programme = Uuid::find(Programme::class, $data['programme_id']);
        $link = $offering->programmes()->firstOrCreate(['programme_id' => $programme->id, 'level' => $data['level']]);
        if (! $link->wasRecentlyCreated) {
            throw ValidationException::withMessages(['programme_id' => 'This cohort is already linked.']);
        }

        return (new OfferingResource($offering->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function removeProgramme(CourseOffering $offering, int $link): Response
    {
        Gate::authorize('offerings.manage', $offering);
        $this->ensureOpen($offering);
        $offering->programmes()->findOrFail($link)->delete();

        return response()->noContent();
    }

    public function assignStaff(Request $request, CourseOffering $offering): JsonResponse
    {
        Gate::authorize('offerings.allocate', $offering);
        $data = $request->validate([
            'staff_id' => ['required', 'string', 'exists:staff,uuid'],
            'role' => ['required', Rule::in(CourseOfferingStaff::ROLES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $this->offerings->assignStaff($offering, Uuid::find(Staff::class, $data['staff_id']), $data['role'], $request->user(), $data['reason'] ?? null);

        return (new OfferingResource($offering->load(self::DETAIL)))->response()->setStatusCode(201);
    }

    public function endAssignment(Request $request, CourseOffering $offering, int $assignment): OfferingResource
    {
        Gate::authorize('offerings.allocate', $offering);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->offerings->endAssignment($offering->staffAssignments()->findOrFail($assignment), $request->user(), $data['reason']);

        return new OfferingResource($offering->load(self::DETAIL));
    }

    private function ensureOpen(CourseOffering $offering): void
    {
        if (! in_array($offering->status, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['offering' => "This offering is {$offering->status} and can no longer be changed."]);
        }
    }
}
