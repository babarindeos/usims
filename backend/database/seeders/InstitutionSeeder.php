<?php

namespace Database\Seeders;

use App\Modules\Institution\Models\OrgUnitType;
use App\Modules\Institution\Models\Setting;
use Illuminate\Database\Seeder;

/** Generic reference data every deployment needs. Institution-specific structure is entered in the admin screens. */
class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['university', 'University', false], ['campus', 'Campus', false], ['college', 'College', true],
            ['faculty', 'Faculty', true], ['school', 'School', true], ['department', 'Department', true],
            ['centre', 'Centre', false], ['institute', 'Institute', true], ['directorate', 'Directorate', false],
            ['unit', 'Administrative Unit', false],
        ];
        foreach ($types as $i => [$code, $name, $academic]) {
            OrgUnitType::firstOrCreate(['code' => $code], ['name' => $name, 'is_academic' => $academic, 'sort_order' => $i]);
        }

        Setting::firstOrCreate(['key' => 'branding'], ['value' => [
            'institution_name' => 'University',
            'portal_title' => 'USIMS',
            'primary_color' => '#0B5D3B',
            'secondary_color' => '#C9A227',
            'logo_url' => null,
        ]]);
    }
}
