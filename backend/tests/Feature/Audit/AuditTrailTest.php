<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\Person;
use App\Modules\Identity\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PRD acceptance scenario 12: who, when, what changed and why. */
class AuditTrailTest extends TestCase
{
    public function test_change_records_who_what_when_and_why(): void
    {
        $officer = $this->userWith(['persons.manage', 'audit.view']);
        $person = Person::factory()->create(['surname' => 'Bello']);

        $this->actingAs($officer)
            ->withHeader('X-Audit-Reason', 'Spelling corrected from birth certificate')
            ->patchJson("/api/v1/persons/{$person->uuid}", ['surname' => 'Bello-Ade'])->assertOk();

        $log = AuditLog::where('auditable_id', $person->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame($officer->id, $log->user_id);
        $this->assertSame(['surname' => 'Bello'], $log->before);
        $this->assertSame(['surname' => 'Bello-Ade'], $log->after);
        $this->assertSame('Spelling corrected from birth certificate', $log->reason);
        $this->assertNotNull($log->created_at);

        $this->actingAs($officer)->getJson('/api/v1/audit-logs?type=Person&record_id='.$person->id)
            ->assertOk()->assertJsonPath('data.0.reason', 'Spelling corrected from birth certificate');
    }

    public function test_passwords_never_reach_the_audit_log(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::where('auditable_type', $user->getMorphClass())->where('auditable_id', $user->id)->first();

        $this->assertStringNotContainsString($user->password, json_encode($log->after));
        $this->assertContains($log->after['password'] ?? '[redacted]', ['[redacted]']);
    }

    public function test_audit_log_is_append_only(): void
    {
        Person::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('audit_logs')->update(['reason' => 'tampered']);
    }

    public function test_audit_view_needs_permission(): void
    {
        $this->actingAs($this->userWith([]))->getJson('/api/v1/audit-logs')->assertForbidden();
    }
}
