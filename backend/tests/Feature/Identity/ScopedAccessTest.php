<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use Tests\TestCase;

class ScopedAccessTest extends TestCase
{
    public function test_department_scope_covers_only_that_department(): void
    {
        ['csc' => $csc, 'mts' => $mts, 'college' => $college] = $this->structure();
        $officer = $this->userWith(['org_units.manage'], $csc);
        $access = app(AccessService::class);

        $this->assertTrue($access->allows($officer, 'org_units.manage', $csc));
        $this->assertFalse($access->allows($officer, 'org_units.manage', $mts));
        $this->assertFalse($access->allows($officer, 'org_units.manage', $college));
    }

    public function test_college_scope_covers_its_departments(): void
    {
        ['csc' => $csc, 'mts' => $mts, 'uni' => $uni, 'college' => $college] = $this->structure();
        $dean = $this->userWith(['org_units.manage'], $college);
        $access = app(AccessService::class);

        $this->assertTrue($access->allows($dean, 'org_units.manage', $csc));
        $this->assertTrue($access->allows($dean, 'org_units.manage', $mts));
        $this->assertFalse($access->allows($dean, 'org_units.manage', $uni));
    }

    public function test_expired_future_and_revoked_grants_do_not_apply(): void
    {
        ['csc' => $csc] = $this->structure();
        $access = app(AccessService::class);

        $expired = $this->userWith(['org_units.manage'], null, ['starts_at' => now()->subWeek(), 'ends_at' => now()->subDay()]);
        $future = $this->userWith(['org_units.manage'], null, ['starts_at' => now()->addDay()]);
        $revoked = $this->userWith(['org_units.manage'], null, ['revoked_at' => now()]);

        foreach ([$expired, $future, $revoked] as $user) {
            $this->assertFalse($access->allows($user, 'org_units.manage', $csc));
        }
    }

    public function test_suspended_user_holds_no_permissions(): void
    {
        $user = $this->userWith(['users.view']);
        $user->update(['status' => 'suspended']);

        $this->assertFalse(app(AccessService::class)->allows($user->fresh(), 'users.view'));
    }

    public function test_api_enforces_scope_not_just_navigation(): void
    {
        ['csc' => $csc, 'mts' => $mts] = $this->structure();
        $officer = $this->userWith(['org_units.manage'], $csc);

        $this->actingAs($officer)->patchJson("/api/v1/org-units/{$csc->uuid}", ['name' => 'Computing'])->assertOk();
        $this->actingAs($officer)->patchJson("/api/v1/org-units/{$mts->uuid}", ['name' => 'Maths'])->assertForbidden();
    }

    public function test_grants_need_authority_in_scope_and_never_to_self(): void
    {
        ['csc' => $csc, 'mts' => $mts] = $this->structure();
        $manager = $this->userWith(['grants.manage'], $csc);
        $target = User::factory()->create();

        $payload = ['role' => 'department_officer', 'scope_type' => 'org_unit', 'reason' => 'New officer'];

        $this->actingAs($manager)->postJson("/api/v1/users/{$target->uuid}/grants", $payload + ['org_unit_id' => $csc->uuid])->assertCreated();
        $this->actingAs($manager)->postJson("/api/v1/users/{$target->uuid}/grants", $payload + ['org_unit_id' => $mts->uuid])->assertForbidden();
        $this->actingAs($manager)->postJson("/api/v1/users/{$target->uuid}/grants", ['role' => 'registry_officer', 'scope_type' => 'global', 'reason' => 'x'])->assertForbidden();
        $this->actingAs($manager)->postJson("/api/v1/users/{$manager->uuid}/grants", $payload + ['org_unit_id' => $csc->uuid])->assertStatus(422);
    }

    public function test_revoked_grant_is_kept_as_history(): void
    {
        $admin = $this->userWith(['grants.manage', 'users.view']);
        $target = User::factory()->create();
        $grant = $this->grant($target, ['users.view']);

        $this->actingAs($admin)->postJson("/api/v1/grants/{$grant->id}/revoke", ['reason' => 'Left the unit'])->assertOk();

        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertFalse(app(AccessService::class)->allows($target, 'users.view'));
        $this->actingAs($admin)->getJson("/api/v1/users/{$target->uuid}/grants?include_history=1")->assertJsonCount(1, 'data');
    }
}
