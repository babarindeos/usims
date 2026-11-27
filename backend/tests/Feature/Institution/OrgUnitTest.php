<?php

namespace Tests\Feature\Institution;

use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Services\OrgUnitService;
use Tests\TestCase;

class OrgUnitTest extends TestCase
{
    public function test_units_form_a_tree_with_paths(): void
    {
        ['uni' => $uni, 'college' => $college, 'csc' => $csc] = $this->structure();

        $this->assertSame("/{$uni->id}/", $uni->path);
        $this->assertSame("/{$uni->id}/{$college->id}/{$csc->id}/", $csc->path);
        $this->assertTrue($college->contains($csc));
    }

    public function test_moving_a_unit_moves_its_children_and_scopes(): void
    {
        ['uni' => $uni, 'college' => $college, 'csc' => $csc] = $this->structure();
        $admin = $this->userWith(['org_units.manage']);
        $this->actingAs($admin)->postJson('/api/v1/org-units', ['parent_id' => $uni->uuid, 'type' => 'college', 'code' => 'COMP', 'name' => 'College of Computing'])->assertCreated();
        $newCollege = OrgUnit::where('code', 'COMP')->first();
        $lab = app(OrgUnitService::class)->create(['parent_id' => $csc->id, 'org_unit_type_id' => $csc->org_unit_type_id, 'code' => 'LAB', 'name' => 'Lab']);

        $this->actingAs($admin)->patchJson("/api/v1/org-units/{$csc->uuid}", ['parent_id' => $newCollege->uuid])->assertOk();

        $this->assertSame("{$newCollege->path}{$csc->id}/", $csc->fresh()->path);
        $this->assertSame("{$newCollege->path}{$csc->id}/{$lab->id}/", $lab->fresh()->path);
        $this->assertFalse($college->fresh()->contains($lab->fresh()));
    }

    public function test_cannot_move_unit_under_its_own_child(): void
    {
        ['college' => $college, 'csc' => $csc] = $this->structure();
        $admin = $this->userWith(['org_units.manage']);

        $this->actingAs($admin)->patchJson("/api/v1/org-units/{$college->uuid}", ['parent_id' => $csc->uuid])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    public function test_active_codes_are_unique(): void
    {
        ['uni' => $uni] = $this->structure();
        $admin = $this->userWith(['org_units.manage']);

        $this->actingAs($admin)->postJson('/api/v1/org-units', ['parent_id' => $uni->uuid, 'type' => 'department', 'code' => 'CSC', 'name' => 'Dup'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_any_signed_in_user_can_read_structure(): void
    {
        $this->structure();
        $this->actingAs($this->userWith([]))->getJson('/api/v1/org-units')->assertOk()->assertJsonCount(4, 'data');
    }
}
