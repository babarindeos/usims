<?php

namespace Tests\Feature\Academic;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Programmes\Models\Programme;

class ProgrammeTest extends AcademicTestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'code' => 'bsc-csc', 'name' => 'B.Sc. Computer Science', 'org_unit_id' => $this->units['csc']->uuid,
            'award_type' => 'BSC', 'category' => 'UG', 'duration_years' => 4, 'min_duration_years' => 4, 'max_duration_years' => 6,
            'study_modes' => [['code' => 'FT'], ['code' => 'PT', 'duration_years' => 5]],
            'entry_levels' => [100, 200],
        ];
    }

    public function test_planning_officer_creates_programme_with_modes_and_entry_levels(): void
    {
        $this->actingAs($this->userWith(['programmes.manage']));
        $response = $this->postJson('/api/v1/programmes', $this->payload())->assertCreated();

        $response->assertJsonPath('data.code', 'BSC-CSC')
            ->assertJsonPath('data.award_type.code', 'BSC')
            ->assertJsonPath('data.entry_levels', [100, 200])
            ->assertJsonCount(2, 'data.study_modes');
        $this->assertSame(5, collect($response->json('data.study_modes'))->firstWhere('code', 'PT')['duration_years']);

        $programme = Programme::where('code', 'BSC-CSC')->first();
        $this->assertTrue(AuditLog::where('auditable_type', $programme->getMorphClass())->where('auditable_id', $programme->id)->where('action', 'created')->exists());
    }

    public function test_programme_changes_are_limited_to_the_users_department(): void
    {
        $this->actingAs($this->userWith(['programmes.manage'], $this->units['csc']));
        $this->postJson('/api/v1/programmes', $this->payload(['org_unit_id' => $this->units['mts']->uuid, 'code' => 'BSC-MTS']))->assertForbidden();
        $id = $this->postJson('/api/v1/programmes', $this->payload())->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/programmes/{$id}", ['org_unit_id' => $this->units['mts']->uuid])->assertForbidden();
        $this->patchJson("/api/v1/programmes/{$id}", ['entry_levels' => [100]])->assertOk()->assertJsonPath('data.entry_levels', [100]);
        $this->assertTrue(AuditLog::where('action', 'entry_levels_changed')->exists());
    }

    public function test_duration_limits_are_validated(): void
    {
        $this->actingAs($this->userWith(['programmes.manage']));
        $this->postJson('/api/v1/programmes', $this->payload(['min_duration_years' => 5] + []))->assertUnprocessable()->assertJsonValidationErrors('min_duration_years');
        $this->postJson('/api/v1/programmes', $this->payload(['award_type' => 'NOPE']))->assertUnprocessable()->assertJsonValidationErrors('award_type');
    }

    public function test_reference_lists_are_institution_defined(): void
    {
        $this->actingAs($this->userWith([]));
        $this->getJson('/api/v1/academic-config/award-types')->assertOk()->assertJsonFragment(['code' => 'BSC']);
        $this->postJson('/api/v1/academic-config/study-modes', ['code' => 'SW', 'name' => 'Sandwich'])->assertForbidden();

        $this->actingAs($this->userWith(['academic_config.manage']));
        $this->postJson('/api/v1/academic-config/study-modes', ['code' => 'SW', 'name' => 'Sandwich'])->assertCreated();
        $this->patchJson('/api/v1/academic-config/study-modes/SW', ['active' => false])->assertOk()->assertJsonPath('data.active', false);
        $this->getJson('/api/v1/academic-config/study-modes')->assertJsonMissing(['code' => 'SW']);
        $this->postJson('/api/v1/academic-config/award-types', ['code' => 'BENG', 'name' => 'Bachelor of Engineering'])->assertJsonValidationErrors('level');
    }
}
