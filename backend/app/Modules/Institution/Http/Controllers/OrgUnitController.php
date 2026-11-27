<?php

namespace App\Modules\Institution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Institution\Http\Requests\OrgUnitRequest;
use App\Modules\Institution\Http\Resources\OrgUnitResource;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\OrgUnitType;
use App\Modules\Institution\Services\OrgUnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** The organisational structure is visible to every signed-in user; changing it needs org_units.manage over the parent. */
class OrgUnitController extends Controller
{
    public function __construct(private OrgUnitService $units, private AccessService $access) {}

    public function types(): JsonResponse
    {
        return response()->json(['data' => OrgUnitType::orderBy('sort_order')->get(['code', 'name', 'is_academic'])]);
    }

    /** Flat list in tree order (by path); the client nests by parent_id. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $units = OrgUnit::query()->with(['parent:id,uuid', 'type', 'head'])
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('status', 'active'))
            ->when($request->query('type'), fn ($q, $t) => $q->whereHas('type', fn ($w) => $w->where('code', $t)))
            ->orderBy('path')
            ->get();

        return OrgUnitResource::collection($units);
    }

    public function show(OrgUnit $orgUnit): OrgUnitResource
    {
        return new OrgUnitResource($orgUnit->load(['parent', 'type', 'head']));
    }

    public function store(OrgUnitRequest $request): JsonResponse
    {
        $parent = $request->validated('parent_id') ? OrgUnit::where('uuid', $request->validated('parent_id'))->firstOrFail() : null;
        $this->authorizeUnder($request, $parent);

        $unit = $this->units->create($this->attributes($request) + ['parent_id' => $parent?->id]);

        return (new OrgUnitResource($unit->load(['parent', 'type', 'head'])))->response()->setStatusCode(201);
    }

    public function update(OrgUnitRequest $request, OrgUnit $orgUnit): OrgUnitResource
    {
        Gate::authorize('org_units.manage', $orgUnit);
        $data = $this->attributes($request);

        if ($request->has('parent_id')) {
            $parent = $request->validated('parent_id') ? OrgUnit::where('uuid', $request->validated('parent_id'))->firstOrFail() : null;
            $this->authorizeUnder($request, $parent);
            $data['parent_id'] = $parent?->id;
        }

        return new OrgUnitResource($this->units->update($orgUnit, $data)->load(['parent', 'type', 'head']));
    }

    private function authorizeUnder(Request $request, ?OrgUnit $parent): void
    {
        $allowed = $parent ? Gate::allows('org_units.manage', $parent) : $this->access->allowsGlobally($request->user(), 'org_units.manage');
        abort_unless($allowed, 403, 'You cannot change the structure at this level.');
    }

    private function attributes(OrgUnitRequest $request): array
    {
        $data = $request->safe()->except(['parent_id', 'type', 'head_person_id']);
        if ($request->has('type')) {
            $data['org_unit_type_id'] = OrgUnitType::where('code', $request->validated('type'))->value('id');
        }
        if ($request->has('head_person_id')) {
            $data['head_person_id'] = $request->validated('head_person_id') ? Person::where('uuid', $request->validated('head_person_id'))->value('id') : null;
        }

        return $data;
    }
}
