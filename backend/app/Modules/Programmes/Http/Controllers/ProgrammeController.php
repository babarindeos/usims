<?php

namespace App\Modules\Programmes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\Uuid;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Programmes\Http\Requests\ProgrammeRequest;
use App\Modules\Programmes\Http\Resources\ProgrammeResource;
use App\Modules\Programmes\Models\AwardType;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\ProgrammeCategory;
use App\Modules\Programmes\Services\ProgrammeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** The programme catalogue is readable by every signed-in user; changes are scoped to the owning department. */
class ProgrammeController extends Controller
{
    public function __construct(private ProgrammeService $programmes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $term = mb_strtolower(trim((string) $request->query('q')));
        $rows = Programme::query()->with(['orgUnit', 'awardType', 'category'])
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas('orgUnit', fn ($w) => $w->where('uuid', $d)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', ["%{$term}%"])->orWhereRaw('lower(code) = ?', [$term])))
            ->orderBy('name')->get();

        return ProgrammeResource::collection($rows);
    }

    public function show(Programme $programme): ProgrammeResource
    {
        return new ProgrammeResource($programme->load(['orgUnit', 'awardType', 'category', 'studyModes', 'entryLevels']));
    }

    public function store(ProgrammeRequest $request): JsonResponse
    {
        $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
        Gate::authorize('programmes.manage', $unit);
        $programme = $this->programmes->save(new Programme, $this->attributes($request) + ['org_unit_id' => $unit->id], $this->modes($request) ?? [], $request->validated('entry_levels') ?? [100]);

        return (new ProgrammeResource($programme->load(['orgUnit', 'awardType', 'category', 'studyModes', 'entryLevels'])))->response()->setStatusCode(201);
    }

    public function update(ProgrammeRequest $request, Programme $programme): ProgrammeResource
    {
        Gate::authorize('programmes.manage', $programme);
        $attributes = $this->attributes($request);
        if ($request->has('org_unit_id')) {
            $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
            Gate::authorize('programmes.manage', $unit);
            $attributes['org_unit_id'] = $unit->id;
        }
        $programme = $this->programmes->save($programme, $attributes, $this->modes($request), $request->validated('entry_levels'));

        return new ProgrammeResource($programme->load(['orgUnit', 'awardType', 'category', 'studyModes', 'entryLevels']));
    }

    private function attributes(ProgrammeRequest $request): array
    {
        $data = $request->safe()->except(['org_unit_id', 'award_type', 'category', 'study_modes', 'entry_levels']);
        if ($request->has('award_type')) {
            $data['award_type_id'] = AwardType::where('code', $request->validated('award_type'))->value('id');
        }
        if ($request->has('category')) {
            $data['programme_category_id'] = ProgrammeCategory::where('code', $request->validated('category'))->value('id');
        }

        return $data;
    }

    /** @return array<string,int|null>|null */
    private function modes(ProgrammeRequest $request): ?array
    {
        if (! $request->has('study_modes')) {
            return null;
        }

        return collect($request->validated('study_modes'))->mapWithKeys(fn ($m) => [$m['code'] => $m['duration_years'] ?? null])->all();
    }
}
