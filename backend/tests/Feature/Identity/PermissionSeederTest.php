<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use Database\Seeders\PermissionSeeder;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    public function test_new_permissions_reach_system_roles_without_undoing_institution_edits(): void
    {
        $admin = Role::where('code', 'system_admin')->first();
        // The institution removed one permission; a release then introduces another.
        $admin->permissions()->detach(Permission::where('code', 'audit.view')->value('id'));
        Permission::where('code', 'staff.manage')->delete();

        $this->seed(PermissionSeeder::class);

        $codes = $admin->permissions()->pluck('code');
        $this->assertContains('staff.manage', $codes);
        $this->assertNotContains('audit.view', $codes);
    }
}
