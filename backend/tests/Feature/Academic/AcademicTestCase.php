<?php

namespace Tests\Feature\Academic;

use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\User;
use App\Modules\Institution\Models\AcademicSession;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\Semester;
use App\Modules\Programmes\Models\AwardType;
use App\Modules\Programmes\Models\Programme;
use App\Modules\Programmes\Models\ProgrammeCategory;
use App\Modules\Staff\Models\Staff;
use Database\Seeders\AcademicSetupSeeder;
use Tests\TestCase;

abstract class AcademicTestCase extends TestCase
{
    protected array $units;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicSetupSeeder::class);
        $this->units = $this->structure();
    }

    protected function programme(OrgUnit $dept, string $code = 'BSC-CSC'): Programme
    {
        return Programme::create([
            'code' => $code, 'name' => "B.Sc. {$dept->name}", 'org_unit_id' => $dept->id,
            'award_type_id' => AwardType::where('code', 'BSC')->value('id'),
            'programme_category_id' => ProgrammeCategory::where('code', 'UG')->value('id'),
            'duration_years' => 4, 'min_duration_years' => 4, 'max_duration_years' => 6,
        ]);
    }

    protected function course(OrgUnit $dept, string $code, int $units = 3, int $level = 100, int $semester = 1): Course
    {
        return Course::create([
            'code' => $code, 'title' => "Course {$code}", 'credit_units' => $units, 'level' => $level,
            'semester_sequence' => $semester, 'org_unit_id' => $dept->id,
        ]);
    }

    protected function academicSession(string $name = '2026/2027'): AcademicSession
    {
        $y = (int) substr($name, 0, 4);

        return AcademicSession::create(['name' => $name, 'starts_on' => "{$y}-09-01", 'ends_on' => ($y + 1).'-08-31']);
    }

    protected function semester(AcademicSession $session, int $sequence = 1): Semester
    {
        $y = (int) substr($session->name, 0, 4);
        $start = $sequence === 1 ? "{$y}-09-01" : ($y + 1).'-02-01';
        $end = $sequence === 1 ? ($y + 1).'-01-31' : ($y + 1).'-07-31';

        return $session->semesters()->create(['name' => $sequence === 1 ? 'First Semester' : 'Second Semester', 'code' => "S{$sequence}", 'sequence' => $sequence, 'starts_on' => $start, 'ends_on' => $end]);
    }

    protected function curriculum(Programme $programme, AcademicSession $session, array $courses, string $status = 'draft', int $required = 0): CurriculumVersion
    {
        $version = CurriculumVersion::create([
            'programme_id' => $programme->id, 'version' => (int) CurriculumVersion::where('programme_id', $programme->id)->max('version') + 1,
            'title' => 'Test curriculum', 'effective_session_id' => $session->id, 'duration_years' => 4,
            'required_credit_units' => $required, 'status' => 'draft',
        ]);
        foreach ($courses as $course) {
            $version->courses()->create([
                'course_id' => $course->id, 'level' => $course->level, 'semester_sequence' => $course->semester_sequence,
                'category' => 'core', 'credit_units' => $course->credit_units,
            ]);
        }
        if ($status !== 'draft') {
            $version->forceFill(['status' => $status])->save();
        }

        return $version;
    }

    protected function staff(OrgUnit $dept, string $no, string $category = 'academic', string $status = 'active'): Staff
    {
        $person = Person::create(['surname' => "Staff{$no}", 'first_name' => 'Test', 'person_no' => "PER-S{$no}"]);

        return Staff::create([
            'person_id' => $person->id, 'staff_no' => $no, 'org_unit_id' => $dept->id, 'category' => $category,
            'employment_status' => $status, 'source' => 'manual',
        ]);
    }

    protected function departmentOfficer(OrgUnit $dept): User
    {
        return $this->userWith(['courses.manage', 'curricula.view', 'curricula.manage', 'offerings.view', 'offerings.manage', 'offerings.allocate', 'staff.view'], $dept);
    }
}
