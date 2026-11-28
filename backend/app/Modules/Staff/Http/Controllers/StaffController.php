<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Support\Uuid;
use App\Modules\Identity\Http\Resources\PersonResource;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Identity\Services\PersonService;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Staff\Http\Requests\StaffRequest;
use App\Modules\Staff\Http\Resources\StaffResource;
use App\Modules\Staff\Models\Staff;
use App\Modules\Staff\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function __construct(private StaffService $staff, private PersonService $persons, private AccessService $access) {}

    /** Lists staff in the departments where the user holds staff.view. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $term = mb_strtolower(trim((string) $request->query('q')));
        $query = Staff::query()->with(['person', 'orgUnit'])
            ->when($request->query('department'), fn ($q, $d) => $q->whereHas('orgUnit', fn ($w) => $w->where('uuid', $d)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('employment_status', $s))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw('lower(staff_no) = ?', [$term])
                ->orWhereHas('person', fn ($p) => $p->whereRaw("lower(surname || ' ' || first_name) like ?", ["%{$term}%"]))));

        $rows = $this->access->restrictToOrgUnits($request->user(), 'staff.view', $query, 'staff.org_unit_id')
            ->orderBy('staff_no')
            ->paginate(min((int) $request->query('per_page', 50), 200));

        return StaffResource::collection($rows);
    }

    public function show(Staff $staff): StaffResource
    {
        Gate::authorize('staff.view', $staff);

        return new StaffResource($staff->load(['person', 'orgUnit']));
    }

    public function store(StaffRequest $request): JsonResponse
    {
        $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
        Gate::authorize('staff.manage', $unit);

        $data = $request->safe()->only(['staff_no', 'category', 'rank', 'institutional_email']) + ['org_unit_id' => $unit->id];

        if ($request->has('person_id')) {
            $person = Uuid::find(Person::class, $request->validated('person_id'));
            if (Staff::where('person_id', $person->id)->exists()) {
                throw ValidationException::withMessages(['person_id' => 'This person already has a staff record.']);
            }
        } else {
            $details = $request->validated('person');
            $matches = $this->persons->possibleDuplicates($details);
            if ($matches->isNotEmpty() && ! $request->boolean('confirm_not_duplicate')) {
                return response()->json([
                    'type' => 'about:blank', 'title' => 'Possible duplicate', 'status' => 409,
                    'detail' => 'People with matching details already exist. Attach the staff record to one of them, or resubmit with confirm_not_duplicate.',
                    'matches' => PersonResource::collection($matches)->resolve(),
                ], 409, ['Content-Type' => 'application/problem+json']);
            }
        }

        $staff = DB::transaction(fn () => $this->staff->create($person ?? $this->persons->create($details), $data));

        return (new StaffResource($staff->load(['person', 'orgUnit'])))->response()->setStatusCode(201);
    }

    public function update(StaffRequest $request, Staff $staff): StaffResource
    {
        Gate::authorize('staff.manage', $staff);
        $data = $request->safe()->only(['staff_no', 'category', 'rank', 'institutional_email', 'employment_status']);
        if ($request->has('org_unit_id')) {
            $unit = Uuid::find(OrgUnit::class, $request->validated('org_unit_id'));
            Gate::authorize('staff.manage', $unit);
            $data['org_unit_id'] = $unit->id;
        }
        $this->staff->update($staff, $data, $request->validated('reason'));

        return new StaffResource($staff->load(['person', 'orgUnit']));
    }
}
