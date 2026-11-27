<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Identity\Http\Requests\RoleRequest;
use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function permissions(): JsonResponse
    {
        Gate::authorize('users.view');

        return response()->json(['data' => Permission::orderBy('module')->orderBy('code')->get(['code', 'module', 'description', 'sensitive'])]);
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('users.view');

        return RoleResource::collection(Role::with('permissions:id,code')->orderBy('name')->get());
    }

    public function show(Role $role): RoleResource
    {
        Gate::authorize('users.view');

        return new RoleResource($role->load('permissions:id,code'));
    }

    public function store(RoleRequest $request, AuditLogger $audit): JsonResponse
    {
        Gate::authorize('roles.manage');
        $role = DB::transaction(function () use ($request, $audit) {
            $role = Role::create($request->safe()->except('permissions'));
            $this->syncPermissions($role, $request->validated('permissions', []), $audit);

            return $role;
        });

        return (new RoleResource($role->load('permissions:id,code')))->response()->setStatusCode(201);
    }

    public function update(RoleRequest $request, Role $role, AuditLogger $audit): RoleResource
    {
        Gate::authorize('roles.manage');
        DB::transaction(function () use ($request, $role, $audit) {
            $role->update($request->safe()->except('permissions'));
            if ($request->has('permissions')) {
                $this->syncPermissions($role, $request->validated('permissions'), $audit);
            }
        });

        return new RoleResource($role->load('permissions:id,code'));
    }

    private function syncPermissions(Role $role, array $codes, AuditLogger $audit): void
    {
        $before = $role->permissions()->pluck('code')->sort()->values()->all();
        $role->permissions()->sync(Permission::whereIn('code', $codes)->pluck('id'));
        $after = collect($codes)->sort()->values()->all();
        if ($before !== $after) {
            $audit->record('permissions_changed', $role, ['permissions' => $before], ['permissions' => $after]);
        }
    }
}
