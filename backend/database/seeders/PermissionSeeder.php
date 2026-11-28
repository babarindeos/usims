<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Illuminate\Database\Seeder;

/** Idempotent: safe to run on every deploy. Never removes permissions an institution added to a role. */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $new = [];
        foreach (config('permissions.permissions') as $code => $def) {
            $permission = Permission::updateOrCreate(['code' => $code], [
                'module' => $def[0], 'description' => $def[1], 'sensitive' => $def[2] ?? false,
            ]);
            if ($permission->wasRecentlyCreated) {
                $new[$code] = $permission->id;
            }
        }

        foreach (config('permissions.roles') as $code => [$name, $workspace, $permissions]) {
            $role = Role::firstOrCreate(['code' => $code], ['name' => $name, 'workspace' => $workspace, 'is_system' => true]);
            if ($role->wasRecentlyCreated) {
                $role->permissions()->sync(Permission::whereIn('code', $permissions)->pluck('id'));
            } else {
                // A permission introduced by a new release goes to the system roles that list it,
                // without touching what the institution changed on existing permissions.
                $added = array_values(array_intersect_key($new, array_flip($permissions)));
                if ($added) {
                    $role->permissions()->syncWithoutDetaching($added);
                }
            }
        }
    }
}
