<?php

namespace App\Modules\Curriculum\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\Uuid;
use App\Modules\Curriculum\Http\Requests\CourseRequest;
use App\Modules\Curriculum\Http\Resources\CourseResource;
use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Services\CourseService;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** The course catalogue is readable by every signed-in user; the owning department maintains it. */
class CourseController extends Controller
{
    public function __construct(private CourseService $courses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = mb_strtolower(trim((string) $request->query('q')));
        $rows = Course::query()->with('orgUnit')
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas('orgUnit', fn ($w) => $w->where('uuid', $d)))
            ->when($request->query('level'), fn ($q, $l) => $q->where('level', (int) $l))
            ->when($request->query('status', 'active'), fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('lower(title) like ?', ["%{$term}%"])
                ->orWhere('code', 'like', Course::normaliseCode($term).'%')))
            ->orderBy('code')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return CourseResource::collection($rows);
    }

    public function show(Course $course): CourseResource
    {
        return new CourseResource($course->load(['orgUnit', 'requisites.requiredCourse']));
    }

    public function store(CourseRequest $request): JsonResponse
    {
        $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
        Gate::authorize('courses.manage', $unit);
        $course = Course::create($request->safe()->except('org_unit_id') + ['org_unit_id' => $unit->id]);

        return (new CourseResource($course->load(['orgUnit', 'requisites'])))->response()->setStatusCode(201);
    }

    public function update(CourseRequest $request, Course $course): CourseResource
    {
        Gate::authorize('courses.manage', $course);
        $data = $request->safe()->except('org_unit_id');
        if ($request->has('org_unit_id')) {
            $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
            Gate::authorize('courses.manage', $unit);
            $data['org_unit_id'] = $unit->id;
        }
        $course->update($data);

        return new CourseResource($course->load(['orgUnit', 'requisites.requiredCourse']));
    }

    public function addRequisite(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('courses.manage', $course);
        $data = $request->validate([
            'course_id' => ['required', 'string', 'exists:courses,uuid'],
            'type' => ['required', Rule::in(['prerequisite', 'corequisite'])],
            'requirement' => ['sometimes', Rule::in(['pass', 'registered'])],
        ]);
        $this->courses->addRequisite($course, Uuid::find(Course::class, $data['course_id']), $data['type'], $data['requirement'] ?? 'pass');

        return (new CourseResource($course->load(['orgUnit', 'requisites.requiredCourse'])))->response()->setStatusCode(201);
    }

    public function removeRequisite(Course $course, int $requisite): Response
    {
        Gate::authorize('courses.manage', $course);
        $course->requisites()->findOrFail($requisite)->delete();

        return response()->noContent();
    }
}
