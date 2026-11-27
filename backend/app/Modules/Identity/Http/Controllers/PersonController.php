<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\PersonRequest;
use App\Modules\Identity\Http\Resources\PersonResource;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Services\PersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PersonController extends Controller
{
    public function __construct(private PersonService $persons) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('persons.view');
        $term = trim((string) $request->query('q'));

        $people = Person::query()->whereNull('merged_into_person_id')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('person_no', $term)
                ->orWhereRaw('lower(email) = ?', [mb_strtolower($term)])
                ->orWhere('phone', $term)
                ->orWhereRaw("lower(surname || ' ' || first_name) like ?", ['%'.mb_strtolower($term).'%'])))
            ->orderBy('surname')->orderBy('first_name')
            ->paginate(min((int) $request->query('per_page', 25), 100));

        return PersonResource::collection($people);
    }

    public function show(Person $person): PersonResource
    {
        Gate::authorize('persons.view');
        DB::table('access_logs')->insert([
            'user_id' => auth()->id(), 'resource_type' => $person->getMorphClass(), 'resource_id' => $person->id,
            'purpose' => 'view', 'ip_address' => request()->ip(), 'created_at' => now(),
        ]);

        return new PersonResource($person->load('identifiers'));
    }

    public function duplicates(PersonRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('persons.manage');

        return PersonResource::collection($this->persons->possibleDuplicates($request->validated()));
    }

    /** Creating a person that matches an existing record needs explicit confirmation. */
    public function store(PersonRequest $request): JsonResponse
    {
        Gate::authorize('persons.manage');
        $data = $request->safe()->except('confirm_not_duplicate');

        $matches = $this->persons->possibleDuplicates($data);
        if ($matches->isNotEmpty() && ! $request->boolean('confirm_not_duplicate')) {
            return response()->json([
                'type' => 'about:blank', 'title' => 'Possible duplicate', 'status' => 409,
                'detail' => 'Records with matching details already exist. Review them, or resubmit with confirm_not_duplicate.',
                'matches' => PersonResource::collection($matches)->resolve(),
            ], 409, ['Content-Type' => 'application/problem+json']);
        }

        return (new PersonResource($this->persons->create($data)))->response()->setStatusCode(201);
    }

    public function update(PersonRequest $request, Person $person): PersonResource
    {
        Gate::authorize('persons.manage');
        $person->update($request->safe()->except('confirm_not_duplicate'));

        return new PersonResource($person);
    }
}
