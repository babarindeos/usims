<?php

namespace App\Modules\Identity\Services;

use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Identity\Models\RoleGrant;
use App\Modules\Identity\Models\User;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Scoped role-based access control (PRD part 16).
 *
 * A user holds grants (role + scope + validity window). A permission is allowed over a target
 * when an effective grant's role carries the permission and the grant's scope covers the target's
 * org unit: global scope covers everything, an org-unit scope covers that unit and every unit below it.
 */
class AccessService
{
    /** @var array<int, Collection> */
    private array $cache = [];

    /** @return Collection<int, array{permissions: array<string,bool>, scope_type: string, path: ?string, org_unit_id: ?int, role_code: string}> */
    public function grantsFor(User $user): Collection
    {
        return $this->cache[$user->id] ??= RoleGrant::query()
            ->effective()
            ->where('user_id', $user->id)
            ->with(['role.permissions:id,code', 'orgUnit:id,path'])
            ->get()
            ->map(fn (RoleGrant $g) => [
                'role_code' => $g->role->code,
                'scope_type' => $g->scope_type,
                'org_unit_id' => $g->org_unit_id,
                'path' => $g->orgUnit?->path,
                'permissions' => $g->role->permissions->pluck('code')->flip()->map(fn () => true)->all(),
            ]);
    }

    public function forget(?User $user = null): void
    {
        if ($user) {
            unset($this->cache[$user->id]);
        } else {
            $this->cache = [];
        }
    }

    /**
     * @param  OrgUnit|BelongsToOrgUnit|null  $target  null asks "does the user hold this permission anywhere?"
     */
    public function allows(User $user, string $permission, OrgUnit|BelongsToOrgUnit|null $target = null): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $unit = $target instanceof BelongsToOrgUnit ? $target->owningOrgUnit() : $target;
        $targetGiven = $target !== null;

        foreach ($this->grantsFor($user) as $grant) {
            if (! isset($grant['permissions'][$permission])) {
                continue;
            }
            if ($grant['scope_type'] === RoleGrant::SCOPE_GLOBAL || ! $targetGiven) {
                return true;
            }
            if ($unit && $grant['path'] && str_starts_with($unit->path, $grant['path'])) {
                return true;
            }
        }

        return false;
    }

    /** The role that gave the permission over the target, for workflow records. */
    public function roleUsed(User $user, string $permission, OrgUnit|BelongsToOrgUnit|null $target = null): ?string
    {
        $unit = $target instanceof BelongsToOrgUnit ? $target->owningOrgUnit() : $target;
        foreach ($this->grantsFor($user) as $grant) {
            if (isset($grant['permissions'][$permission]) && ($grant['scope_type'] === RoleGrant::SCOPE_GLOBAL
                || ($unit && $grant['path'] && str_starts_with($unit->path, $grant['path'])))) {
                return $grant['role_code'];
            }
        }

        return null;
    }

    /**
     * Limit a query to records the user may see under a permission.
     * $pathColumn is a qualified column holding the owning org unit's path (join it in first),
     * or use scopeByOrgUnitIds() with an org-unit id column.
     */
    public function restrictToOrgUnits(User $user, string $permission, Builder $query, string $orgUnitIdColumn): Builder
    {
        $paths = [];
        foreach ($this->grantsFor($user) as $grant) {
            if (! isset($grant['permissions'][$permission])) {
                continue;
            }
            if ($grant['scope_type'] === RoleGrant::SCOPE_GLOBAL) {
                return $query;
            }
            $paths[] = $grant['path'];
        }

        if ($paths === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($orgUnitIdColumn, function ($sub) use ($paths) {
            $sub->select('id')->from('org_units')->where(function ($w) use ($paths) {
                foreach ($paths as $path) {
                    $w->orWhere('path', 'like', $path.'%');
                }
            });
        });
    }

    /** True when the user holds the permission with university-wide scope. */
    public function allowsGlobally(User $user, string $permission): bool
    {
        return $user->isActive() && $this->grantsFor($user)->contains(
            fn ($g) => isset($g['permissions'][$permission]) && $g['scope_type'] === RoleGrant::SCOPE_GLOBAL);
    }

    /** Every permission code the user holds in at least one scope (drives navigation). */
    public function permissionCodes(User $user): array
    {
        return $this->grantsFor($user)->flatMap(fn ($g) => array_keys($g['permissions']))->unique()->values()->all();
    }
}
