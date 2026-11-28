<?php

namespace Database\Seeders;

use App\Modules\Curriculum\Services\CurriculumService;
use App\Modules\Institution\Models\Setting;
use App\Modules\Programmes\Models\AwardType;
use App\Modules\Programmes\Models\ProgrammeCategory;
use App\Modules\Programmes\Models\StudyMode;
use App\Modules\Workflow\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

/**
 * Starting reference data for academic setup. Every value is an editable record: institutions rename,
 * deactivate or add their own from the Academic configuration screen. Existing rows are never changed.
 */
class AcademicSetupSeeder extends Seeder
{
    public function run(): void
    {
        $awards = [
            ['BSC', 'Bachelor of Science', 'undergraduate'], ['BTECH', 'Bachelor of Technology', 'undergraduate'],
            ['BAGRIC', 'Bachelor of Agriculture', 'undergraduate'], ['BA', 'Bachelor of Arts', 'undergraduate'],
            ['PGD', 'Postgraduate Diploma', 'postgraduate'], ['MSC', 'Master of Science', 'postgraduate'],
            ['PHD', 'Doctor of Philosophy', 'postgraduate'], ['DIP', 'Diploma', 'sub_degree'],
        ];
        foreach ($awards as [$code, $name, $level]) {
            AwardType::firstOrCreate(['code' => $code], ['name' => $name, 'level' => $level]);
        }
        foreach ([['FT', 'Full-time'], ['PT', 'Part-time'], ['DL', 'Distance learning']] as [$code, $name]) {
            StudyMode::firstOrCreate(['code' => $code], ['name' => $name]);
        }
        foreach ([['UG', 'Undergraduate'], ['PG', 'Postgraduate'], ['SUB', 'Sub-degree'], ['CERT', 'Certificate']] as [$code, $name]) {
            ProgrammeCategory::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        Setting::firstOrCreate(['key' => 'teaching_eligible_categories'], ['value' => ['academic']]);
        Setting::firstOrCreate(['key' => 'lms_shortname_format'], ['value' => '{code}-{session}-{semester}{group}']);

        if (! WorkflowDefinition::where('process', CurriculumService::PROCESS)->exists()) {
            $definition = WorkflowDefinition::create([
                'process' => CurriculumService::PROCESS, 'code' => 'curriculum_approval_default',
                'name' => 'Curriculum approval', 'status' => 'active',
            ]);
            $definition->stages()->createMany([
                ['sequence' => 1, 'code' => 'department', 'name' => 'Head of Department endorsement', 'permission_code' => 'curricula.endorse', 'due_in_hours' => 120],
                ['sequence' => 2, 'code' => 'academic_planning', 'name' => 'Academic Planning approval', 'permission_code' => 'curricula.approve', 'due_in_hours' => 240],
            ]);
        }
    }
}
