<?php

namespace App\Modules\Curriculum\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditContext;
use App\Modules\Core\Support\Uuid;
use App\Modules\Curriculum\Http\Requests\CurriculumRequest;
use App\Modules\Curriculum\Http\Resources\CurriculumResource;
use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Models\CurriculumCourse;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Curriculum\Services\CurriculumService;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Published and retired curricula are visible to every signed-in user. Drafts and versions under review
 * are visible to holders of curricula.view in the programme's department.
 */
class CurriculumController extends Controller
{
    private const PUBLIC_STATUSES = ['published', 'retired'];

    private const WITH = ['programme.orgUnit', 'effectiveSession', 'basedOn', 'courses.course', 'electiveGroups'];

    public function __construct(private CurriculumService $curricula, private AccessService $access) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $visible = $this->access->restrictToOrgUnits($user, 'curricula.view', Programme::query()->select('programmes.id'), 'org_unit_id');

        $rows = CurriculumVersion::query()->with(['programme.orgUnit', 'effectiveSession', 'basedOn'])
            ->where(fn ($q) => $q->whereIn('status', self::PUBLIC_STATUSES)->orWhereIn('programme_id', $visible))
            ->when($request->query('programme'), fn ($q, $p) => $q->whereHas('programme', fn ($w) => $w->where('uuid', $p)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('programme_id')->orderByDesc('version')
            ->get();

        return CurriculumResource::collection($rows);
    }

    public function show(Request $request, CurriculumVersion $curriculum): CurriculumResource
    {
        if (! in_array($curriculum->status, self::PUBLIC_STATUSES, true)) {
            Gate::authorize('curricula.view', $curriculum);
        }

        return new CurriculumResource($curriculum->load(self::WITH));
    }

    public function store(CurriculumRequest $request): JsonResponse
    {
        $programme = Uuid::find(Programme::class, $request->validated('programme_id'));
        Gate::authorize('curricula.manage', $programme);
        $basedOn = Uuid::find(CurriculumVersion::class, $request->validated('based_on_id'));
        if ($basedOn && $basedOn->programme_id !== $programme->id) {
            abort(422, 'The version to copy belongs to a different programme.');
        }

        $version = $this->curricula->createVersion($programme, $request->safe()->except(['programme_id', 'based_on_id']), $request->user(), $basedOn);

        return (new CurriculumResource($version->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(CurriculumRequest $request, CurriculumVersion $curriculum): CurriculumResource
    {
        Gate::authorize('curricula.manage', $curriculum);
        $this->curricula->updateVersion($curriculum, $request->validated());

        return new CurriculumResource($curriculum->load(self::WITH));
    }

    public function addCourse(Request $request, CurriculumVersion $curriculum): JsonResponse
    {
        Gate::authorize('curricula.manage', $curriculum);
        $data = $request->validate($this->courseRules(true));
        $this->curricula->addCourse($curriculum, Uuid::find(Course::class, $data['course_id']), $data);

        return (new CurriculumResource($curriculum->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function updateCourse(Request $request, CurriculumVersion $curriculum, int $row): CurriculumResource
    {
        Gate::authorize('curricula.manage', $curriculum);
        $mapped = CurriculumCourse::where('curriculum_version_id', $curriculum->id)->findOrFail($row);
        $this->curricula->updateCourse($mapped, $request->validate($this->courseRules(false)));

        return new CurriculumResource($curriculum->load(self::WITH));
    }

    public function removeCourse(CurriculumVersion $curriculum, int $row): Response
    {
        Gate::authorize('curricula.manage', $curriculum);
        $this->curricula->removeCourse(CurriculumCourse::where('curriculum_version_id', $curriculum->id)->findOrFail($row));

        return response()->noContent();
    }

    public function addGroup(Request $request, CurriculumVersion $curriculum): JsonResponse
    {
        Gate::authorize('curricula.manage', $curriculum);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'between:100,900', 'multiple_of:100'],
            'semester_sequence' => ['required', 'integer', 'between:1,4'],
            'min_units' => ['required', 'integer', 'between:0,60'],
            'max_units' => ['nullable', 'integer', 'between:1,60', 'gte:min_units'],
        ]);
        $this->curricula->addGroup($curriculum, $data);

        return (new CurriculumResource($curriculum->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function removeGroup(CurriculumVersion $curriculum, int $group): Response
    {
        Gate::authorize('curricula.manage', $curriculum);
        $this->curricula->removeGroup($curriculum->electiveGroups()->findOrFail($group));

        return response()->noContent();
    }

    public function submit(Request $request, CurriculumVersion $curriculum): CurriculumResource
    {
        Gate::authorize('curricula.manage', $curriculum);
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:2000']])['remarks'] ?? null;
        $this->curricula->submit($curriculum, $request->user(), $remarks);

        return new CurriculumResource($curriculum->refresh()->load(self::WITH));
    }

    public function publish(Request $request, CurriculumVersion $curriculum): CurriculumResource
    {
        Gate::authorize('curricula.publish', $curriculum);
        $this->curricula->publish($curriculum, $request->user());

        return new CurriculumResource($curriculum->load(self::WITH));
    }

    public function retire(Request $request, CurriculumVersion $curriculum): CurriculumResource
    {
        Gate::authorize('curricula.publish', $curriculum);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];
        app(AuditContext::class)->withReason($reason, fn () => $this->curricula->retire($curriculum));

        return new CurriculumResource($curriculum->load(self::WITH));
    }

    private function courseRules(bool $creating): array
    {
        return array_filter([
            'course_id' => $creating ? ['required', 'string', 'exists:courses,uuid'] : null,
            'level' => ['sometimes', 'integer', 'between:100,900', 'multiple_of:100'],
            'semester_sequence' => ['sometimes', 'integer', 'between:1,4'],
            'category' => [$creating ? 'required' : 'sometimes', Rule::in(CurriculumCourse::CATEGORIES)],
            'credit_units' => ['sometimes', 'integer', 'between:0,30'],
            'elective_group_id' => ['nullable', 'integer'],
        ]);
    }
}
