<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\GrantRequest;
use App\Modules\Identity\Http\Resources\GrantResource;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleGrant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GrantController extends Controller
{
    public function index(Request $request, User $user): AnonymousResourceCollection
    {
        Gate::authorize('users.view');
        $grants = $user->grants()->with(['role', 'orgUnit'])
            ->when(! $request->boolean('include_history'), fn ($q) => $q->effective())
            ->orderByDesc('starts_at')->get();

        return GrantResource::collection($grants);
    }

    /** Global grants need a global grants.manage; scoped grants need it over that unit. Nobody grants to themselves. */
    public function store(GrantRequest $request, User $user, AccessService $access): JsonResponse
    {
        $unit = $request->validated('org_unit_id') ? OrgUnit::where('uuid', $request->validated('org_unit_id'))->firstOrFail() : null;
        if ($unit ? ! Gate::allows('grants.manage', $unit) : ! $access->allowsGlobally($request->user(), 'grants.manage')) {
            abort(403, 'You cannot grant roles in this scope.');
        }
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['role' => 'You cannot grant a role to yourself.']);
        }

        $grant = RoleGrant::create([
            'user_id' => $user->id,
            'role_id' => Role::where('code', $request->validated('role'))->value('id'),
            'scope_type' => $request->validated('scope_type'),
            'org_unit_id' => $unit?->id,
            'starts_at' => $request->validated('starts_at') ?? now(),
            'ends_at' => $request->validated('ends_at'),
            'granted_by' => $request->user()->id,
            'reason' => $request->validated('reason'),
        ]);
        $access->forget($user);

        return (new GrantResource($grant->load(['role', 'orgUnit'])))->response()->setStatusCode(201);
    }

    /** Grants are end-dated, never deleted, so history shows who held what and when. */
    public function revoke(Request $request, RoleGrant $grant, AccessService $access): GrantResource
    {
        $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $grant->load('orgUnit');
        if ($grant->orgUnit ? ! Gate::allows('grants.manage', $grant->orgUnit) : ! $access->allowsGlobally($request->user(), 'grants.manage')) {
            abort(403);
        }
        if ($grant->revoked_at) {
            throw ValidationException::withMessages(['grant' => 'This grant is already revoked.']);
        }
        $grant->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id, 'reason' => $request->input('reason')]);
        $access->forget($grant->user);

        return new GrantResource($grant->load(['role', 'orgUnit']));
    }
}
