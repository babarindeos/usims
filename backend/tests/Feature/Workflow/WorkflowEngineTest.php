<?php

namespace Tests\Feature\Workflow;

use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Workflow\Events\WorkflowCompleted;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** Uses an org unit as a stand-in subject; real modules attach their own records. */
class WorkflowEngineTest extends TestCase
{
    private array $units;

    protected function setUp(): void
    {
        parent::setUp();
        $this->units = $this->structure();
        $this->definition('two_stage', [], [
            ['code' => 'hod', 'name' => 'HOD review', 'permission_code' => 'org_units.manage'],
            ['code' => 'registry', 'name' => 'Registry approval', 'permission_code' => 'persons.manage'],
        ]);
    }

    private function definition(string $code, array $conditions, array $stages, bool $self = false): WorkflowDefinition
    {
        $d = WorkflowDefinition::create(['process' => 'demo', 'code' => $code, 'name' => "Demo {$code}", 'conditions' => $conditions, 'allow_self_approval' => $self]);
        foreach ($stages as $i => $s) {
            $d->stages()->create($s + ['sequence' => $i + 1]);
        }

        return $d;
    }

    private function engine(): WorkflowEngine
    {
        return app(WorkflowEngine::class);
    }

    public function test_two_stage_approval_by_scoped_officers(): void
    {
        Event::fake([WorkflowCompleted::class]);
        $csc = $this->units['csc'];
        $maker = User::factory()->create();
        $hod = $this->userWith(['org_units.manage'], $csc);
        $registrar = $this->userWith(['persons.manage']);

        $wf = $this->engine()->start('demo', $csc, $maker);
        $this->assertSame('hod', $wf->currentStage->code);
        $this->assertCount(1, $this->engine()->pendingFor($hod));
        $this->assertCount(0, $this->engine()->pendingFor($registrar));

        $wf = $this->engine()->act($wf, $hod, 'approve', 'Fine');
        $this->assertSame('registry', $wf->currentStage->code);

        $wf = $this->engine()->act($wf, $registrar, 'approve');
        $this->assertSame('approved', $wf->status);
        $this->assertCount(3, $wf->actions);
        Event::assertDispatched(WorkflowCompleted::class, fn ($e) => $e->outcome === 'approved');
    }

    public function test_officer_outside_scope_cannot_act(): void
    {
        $wf = $this->engine()->start('demo', $this->units['csc'], User::factory()->create());
        $otherHod = $this->userWith(['org_units.manage'], $this->units['mts']);

        $this->expectException(WorkflowException::class);
        $this->engine()->act($wf, $otherHod, 'approve');
    }

    public function test_maker_cannot_approve_own_submission(): void
    {
        $hod = $this->userWith(['org_units.manage'], $this->units['csc']);
        $wf = $this->engine()->start('demo', $this->units['csc'], $hod);

        $this->assertCount(0, $this->engine()->pendingFor($hod));
        $this->actingAs($hod)->postJson("/api/v1/workflows/{$wf->uuid}/actions", ['action' => 'approve'])
            ->assertForbidden()->assertJsonPath('detail', 'You cannot approve or reject a submission you made yourself.');
    }

    public function test_return_resubmit_and_reject_with_reasons(): void
    {
        $maker = User::factory()->create();
        $hod = $this->userWith(['org_units.manage'], $this->units['csc']);
        $wf = $this->engine()->start('demo', $this->units['csc'], $maker);

        $this->actingAs($hod)->postJson("/api/v1/workflows/{$wf->uuid}/actions", ['action' => 'return'])->assertStatus(422);
        $wf = $this->engine()->act($wf, $hod, 'return', 'Attach the minutes');
        $this->assertSame('returned', $wf->status);

        $wf = $this->engine()->act($wf, $maker, 'resubmit', 'Attached');
        $this->assertSame('in_progress', $wf->status);

        $wf = $this->engine()->act($wf, $hod, 'reject', 'Not supported by the board');
        $this->assertSame('rejected', $wf->status);
        $this->assertSame(1, UserNotification::where('user_id', $maker->id)->count());
    }

    public function test_most_specific_definition_wins(): void
    {
        $this->definition('csc_only', ['department' => 'CSC'], [['code' => 'auto', 'name' => 'Auto', 'permission_code' => 'users.view']]);

        $this->assertSame('csc_only', $this->engine()->resolveDefinition('demo', ['department' => 'CSC'])->code);
        $this->assertSame('two_stage', $this->engine()->resolveDefinition('demo', ['department' => 'MTS'])->code);
    }

    public function test_definition_without_stages_auto_approves(): void
    {
        $this->definition('auto', ['mode' => 'auto'], []);
        $wf = $this->engine()->start('demo', $this->units['mts'], User::factory()->create(), ['mode' => 'auto']);

        $this->assertSame('approved', $wf->status);
    }

    public function test_one_open_approval_per_record(): void
    {
        $this->engine()->start('demo', $this->units['csc'], User::factory()->create());
        $this->expectException(WorkflowException::class);
        $this->engine()->start('demo', $this->units['csc'], User::factory()->create());
    }

    public function test_definitions_are_configured_through_the_api(): void
    {
        $admin = $this->userWith(['workflows.manage']);

        $this->actingAs($admin)->postJson('/api/v1/workflow-definitions', [
            'process' => 'course_registration', 'code' => 'reg.default', 'name' => 'Course registration',
            'stages' => [
                ['code' => 'adviser', 'name' => 'Course adviser', 'permission_code' => 'users.view'],
                ['code' => 'hod', 'name' => 'HOD', 'permission_code' => 'org_units.manage'],
            ],
        ])->assertCreated()->assertJsonCount(2, 'data.stages');
    }
}
