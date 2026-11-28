<?php

namespace Database\Seeders;

use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\RoleGrant;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\PersonService;
use App\Modules\Institution\Models\AcademicSession;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\OrgUnitType;
use App\Modules\Institution\Services\OrgUnitService;
use App\Modules\Programmes\Models\AwardType;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\ProgrammeCategory;
use App\Modules\Programmes\Models\StudyMode;
use App\Modules\Staff\Models\Staff;
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
        $mts = $units->create(['parent_id' => $cops->id, 'org_unit_type_id' => $type('department'), 'code' => 'MTS', 'name' => 'Department of Mathematics']);
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

        $this->academicSetup($persons, $csc, $mts);
    }

    /** One session, a programme with a draft curriculum, a course catalogue and a few staff. Nothing is published. */
    private function academicSetup(PersonService $persons, OrgUnit $csc, OrgUnit $mts): void
    {
        $session = AcademicSession::create(['name' => '2026/2027', 'starts_on' => '2026-09-07', 'ends_on' => '2027-08-31', 'is_current' => true]);
        $session->semesters()->createMany([
            ['name' => 'First Semester', 'code' => 'FIRST', 'sequence' => 1, 'starts_on' => '2026-09-07', 'ends_on' => '2027-01-29'],
            ['name' => 'Second Semester', 'code' => 'SECOND', 'sequence' => 2, 'starts_on' => '2027-02-15', 'ends_on' => '2027-07-30'],
        ]);

        $programme = Programme::create([
            'code' => 'BSC-CSC', 'name' => 'B.Sc. Computer Science', 'org_unit_id' => $csc->id,
            'award_type_id' => AwardType::where('code', 'BSC')->value('id'),
            'programme_category_id' => ProgrammeCategory::where('code', 'UG')->value('id'),
            'duration_years' => 4, 'min_duration_years' => 4, 'max_duration_years' => 6, 'capacity' => 150,
        ]);
        $programme->studyModes()->attach(StudyMode::where('code', 'FT')->value('id'));
        $programme->entryLevels()->createMany([['level' => 100], ['level' => 200]]);

        $catalogue = [
            [$csc, 'CSC 101', 'Introduction to Computing', 3, 100, 1], [$csc, 'CSC 102', 'Introduction to Programming', 3, 100, 2],
            [$csc, 'CSC 201', 'Data Structures', 3, 200, 1], [$csc, 'CSC 202', 'Computer Architecture', 2, 200, 2],
            [$mts, 'MTS 101', 'Elementary Mathematics I', 3, 100, 1], [$mts, 'MTS 102', 'Elementary Mathematics II', 3, 100, 2],
        ];
        $courses = [];
        foreach ($catalogue as [$dept, $code, $title, $units, $level, $sem]) {
            $courses[$code] = Course::create(['code' => $code, 'title' => $title, 'credit_units' => $units, 'level' => $level, 'semester_sequence' => $sem, 'org_unit_id' => $dept->id]);
        }
        $courses['CSC 201']->requisites()->create(['required_course_id' => $courses['CSC 102']->id, 'type' => 'prerequisite']);

        $version = CurriculumVersion::create([
            'programme_id' => $programme->id, 'version' => 1, 'title' => 'B.Sc. Computer Science curriculum v1',
            'effective_session_id' => $session->id, 'duration_years' => 4, 'required_credit_units' => 17, 'status' => 'draft',
        ]);
        foreach ($courses as $course) {
            $version->courses()->create(['course_id' => $course->id, 'level' => $course->level, 'semester_sequence' => $course->semester_sequence, 'category' => 'core', 'credit_units' => $course->credit_units]);
        }

        foreach ([['SP/0001', 'Okafor', 'Ngozi', 'Senior Lecturer', 'academic', $csc], ['SP/0002', 'Bello', 'Ibrahim', 'Lecturer I', 'academic', $csc],
            ['SP/0003', 'Ade', 'Funmi', 'Professor', 'academic', $mts], ['SP/0004', 'Eze', 'Chidi', 'Executive Officer', 'administrative', $csc]] as [$no, $surname, $first, $rank, $category, $dept]) {
            $person = $persons->create(['surname' => $surname, 'first_name' => $first]);
            Staff::create(['person_id' => $person->id, 'staff_no' => $no, 'org_unit_id' => $dept->id, 'category' => $category, 'rank' => $rank, 'employment_status' => 'active', 'source' => 'demo']);
        }
    }
}
