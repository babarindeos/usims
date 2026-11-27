<?php

namespace Tests;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleGrant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\OrgUnitType;
use App\Modules\Institution\Services\OrgUnitService;
use Database\Seeders\InstitutionSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, InstitutionSeeder::class, NotificationTemplateSeeder::class]);
    }

    /** A user holding a role made of the given permissions, globally or over one org unit. */
    protected function userWith(array $permissions, ?OrgUnit $scope = null, array $grantOverrides = []): User
    {
        $user = User::factory()->create();
        $this->grant($user, $permissions, $scope, $grantOverrides);

        return $user;
    }

    protected function grant(User $user, array $permissions, ?OrgUnit $scope = null, array $overrides = []): RoleGrant
    {
        static $n = 0;
        $n++;
        $role = Role::create(['code' => "test_role_{$n}", 'name' => "Test role {$n}"]);
        $role->permissions()->sync(Permission::whereIn('code', $permissions)->pluck('id'));
        app(AccessService::class)->forget();

        return RoleGrant::create($overrides + [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => $scope ? RoleGrant::SCOPE_ORG_UNIT : RoleGrant::SCOPE_GLOBAL,
            'org_unit_id' => $scope?->id,
            'starts_at' => now()->subMinute(),
        ]);
    }

    /** University > College > two departments. */
    protected function structure(): array
    {
        $svc = app(OrgUnitService::class);
        $type = fn ($c) => OrgUnitType::where('code', $c)->value('id');
        $uni = $svc->create(['org_unit_type_id' => $type('university'), 'code' => 'UNI', 'name' => 'Test University']);
        $college = $svc->create(['parent_id' => $uni->id, 'org_unit_type_id' => $type('college'), 'code' => 'COL', 'name' => 'College of Sciences']);
        $csc = $svc->create(['parent_id' => $college->id, 'org_unit_type_id' => $type('department'), 'code' => 'CSC', 'name' => 'Computer Science']);
        $mts = $svc->create(['parent_id' => $college->id, 'org_unit_type_id' => $type('department'), 'code' => 'MTS', 'name' => 'Mathematics']);

        return compact('uni', 'college', 'csc', 'mts');
    }
}
