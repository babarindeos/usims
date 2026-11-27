<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleGrant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PersonService;
use App\Modules\Institution\Models\OrgUnitType;
use App\Modules\Institution\Services\OrgUnitService;
use Illuminate\Database\Seeder;

/**
 * Local development data only: a small fictional university and one account per workspace.
 * Every demo account uses the password "password". Never run in production.
 */
class DemoSeeder extends Seeder
{
    public function run(PersonService $persons, OrgUnitService $units): void
    {
        if (User::where('email', 'admin@usims.test')->exists()) {
            return;
        }
        $type = fn (string $code) => OrgUnitType::where('code', $code)->value('id');

        $uni = $units->create(['org_unit_type_id' => $type('university'), 'code' => 'UNI', 'name' => 'Demo University of Agriculture']);
        $cops = $units->create(['parent_id' => $uni->id, 'org_unit_type_id' => $type('college'), 'code' => 'COLPHYS', 'name' => 'College of Physical Sciences']);
        $csc = $units->create(['parent_id' => $cops->id, 'org_unit_type_id' => $type('department'), 'code' => 'CSC', 'name' => 'Department of Computer Science']);
        $units->create(['parent_id' => $cops->id, 'org_unit_type_id' => $type('department'), 'code' => 'MTS', 'name' => 'Department of Mathematics']);
        $units->create(['parent_id' => $uni->id, 'org_unit_type_id' => $type('directorate'), 'code' => 'REG', 'name' => 'Registry']);
        $units->create(['parent_id' => $uni->id, 'org_unit_type_id' => $type('directorate'), 'code' => 'BUR', 'name' => 'Bursary']);

        $accounts = [
            ['admin', 'System', 'Administrator', 'system_admin', null],
            ['registry', 'Registry', 'Officer', 'registry_officer', null],
            ['planning', 'Planning', 'Officer', 'academic_planning_officer', null],
            ['bursary', 'Bursary', 'Officer', 'bursary_officer', null],
            ['cscofficer', 'Department', 'Officer', 'department_officer', $csc->id],
            ['cschod', 'Head', 'Computing', 'head_of_department', $csc->id],
        ];
        foreach ($accounts as [$handle, $first, $surname, $role, $unitId]) {
            $person = $persons->create(['surname' => $surname, 'first_name' => $first, 'email' => "{$handle}@usims.test"]);
            $user = User::create([
                'person_id' => $person->id, 'name' => "{$first} {$surname}", 'email' => "{$handle}@usims.test",
                'password' => 'password', 'status' => 'active',
            ]);
            RoleGrant::create([
                'user_id' => $user->id, 'role_id' => Role::where('code', $role)->value('id'),
                'scope_type' => $unitId ? RoleGrant::SCOPE_ORG_UNIT : RoleGrant::SCOPE_GLOBAL,
                'org_unit_id' => $unitId, 'starts_at' => now(), 'reason' => 'Demo data',
            ]);
        }
    }
}
