<?php

namespace App\Modules\Workflow\Services;

use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\AccessService;
use App\Modules\Identity\Services\IdentifierService;
use App\Modules\Workflow\Events\WorkflowCompleted;
use App\Modules\Workflow\Events\WorkflowStageChanged;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\WorkflowAction;
use App\Modules\Workflow\Models\WorkflowDefinition;
use App\Modules\Workflow\Models\WorkflowInstance;
use App\Modules\Workflow\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Configurable approval engine (PRD part 17). Modules start an instance for a subject record and
 * react to WorkflowCompleted; stage order, approvers and self-approval rules come from definitions.
 */
class WorkflowEngine
{
    public const ACTIONS = ['approve', 'reject', 'return', 'resubmit', 'cancel'];

    public function __construct(private AccessService $access, private IdentifierService $ids) {}

    /** The active definition for a process whose conditions match; the most specific wins. */
    public function resolveDefinition(string $process, array $context = []): WorkflowDefinition
    {
        $definition = WorkflowDefinition::query()
            ->where('process', $process)->where('status', 'active')
            ->get()
            ->filter(fn (WorkflowDefinition $d) => $d->matches($context))
            ->sortByDesc(fn (WorkflowDefinition $d) => [count($d->conditions ?? []), $d->priority])
            ->first();

        if (! $definition) {
            throw new WorkflowException("No active workflow is configured for {$process}.");
        }

        return $definition;
    }

    public function start(string $process, Model $subject, User $initiator, array $context = [], ?string $remarks = null): WorkflowInstance
    {
        $definition = $this->resolveDefinition($process, $context);

        return $this->afterCommit(DB::transaction(function () use ($definition, $subject, $initiator, $remarks) {
            $open = WorkflowInstance::query()
                ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
                ->whereIn('status', [WorkflowInstance::IN_PROGRESS, WorkflowInstance::RETURNED])
                ->lockForUpdate()->exists();
            if ($open) {
                throw new WorkflowException('This record already has an approval in progress.');
            }

            $first = $definition->stages()->first();
            $instance = WorkflowInstance::create([
                'reference' => sprintf('WF-%s-%06d', now()->format('Y'), $this->ids->next('workflow:'.now()->format('Y'))),
                'workflow_definition_id' => $definition->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'org_unit_id' => $subject instanceof BelongsToOrgUnit ? $subject->owningOrgUnit()?->id : null,
                'current_stage_id' => $first?->id,
                'status' => $first ? WorkflowInstance::IN_PROGRESS : WorkflowInstance::APPROVED,
                'initiated_by' => $initiator->id,
                'due_at' => $first?->due_in_hours ? now()->addHours($first->due_in_hours) : null,
                'completed_at' => $first ? null : now(),
            ]);

            $this->log($instance, null, $initiator, null, 'submit', $remarks, null, $instance->status);

            return [$instance, $first ? null : WorkflowInstance::APPROVED, 'submit'];
        }));
    }

    public function act(WorkflowInstance $instance, User $user, string $action, ?string $remarks = null): WorkflowInstance
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new WorkflowException("Unknown action {$action}.");
        }

        return $this->afterCommit(DB::transaction(function () use ($instance, $user, $action, $remarks) {
            $instance = WorkflowInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $instance->load(['definition', 'currentStage', 'orgUnit']);
            $stage = $instance->currentStage;
            $from = $instance->status;

            if (in_array($action, ['resubmit', 'cancel'], true)) {
                if ($instance->initiated_by !== $user->id) {
                    throw new WorkflowException('Only the person who submitted this can '.$action.' it.', 403);
                }
                if ($action === 'resubmit' && $from !== WorkflowInstance::RETURNED) {
                    throw new WorkflowException('Only a returned submission can be resubmitted.');
                }
                if ($action === 'cancel' && ! $instance->isOpen()) {
                    throw new WorkflowException('This approval is already closed.');
                }
                $instance->status = $action === 'resubmit' ? WorkflowInstance::IN_PROGRESS : WorkflowInstance::CANCELLED;
                $instance->completed_at = $action === 'cancel' ? now() : null;
                $instance->save();
                $this->log($instance, $stage, $user, null, $action, $remarks, $from, $instance->status);

                return [$instance, null, $action];
            }

            if ($from !== WorkflowInstance::IN_PROGRESS || ! $stage) {
                throw new WorkflowException('This approval is not waiting for a decision.');
            }
            if (! $this->access->allows($user, $stage->permission_code, $instance)) {
                throw new WorkflowException('You are not authorised to act at the '.$stage->name.' stage.', 403);
            }
            if ($instance->initiated_by === $user->id && ! $instance->definition->allow_self_approval) {
                throw new WorkflowException('You cannot approve or reject a submission you made yourself.', 403);
            }
            if (in_array($action, ['reject', 'return'], true) && blank($remarks)) {
                throw new WorkflowException('A reason is required to '.$action.' a submission.');
            }
            if ($action === 'return' && ! $stage->can_return) {
                throw new WorkflowException('This stage cannot return submissions for correction.');
            }

            $outcome = null;
            $role = $this->access->roleUsed($user, $stage->permission_code, $instance);

            if ($action === 'approve') {
                $next = $this->nextStage($stage);
                if ($next) {
                    $instance->current_stage_id = $next->id;
                    $instance->due_at = $next->due_in_hours ? now()->addHours($next->due_in_hours) : null;
                } else {
                    $instance->status = $outcome = WorkflowInstance::APPROVED;
                    $instance->completed_at = now();
                }
            } elseif ($action === 'reject') {
                $instance->status = $outcome = WorkflowInstance::REJECTED;
                $instance->completed_at = now();
            } else {
                $instance->status = WorkflowInstance::RETURNED;
            }

            $instance->save();
            $this->log($instance, $stage, $user, $role, $action, $remarks, $from, $instance->status);

            return [$instance, $outcome, $action];
        }));
    }

    /** Open instances waiting at a stage the user may act on. */
    public function pendingFor(User $user): Collection
    {
        $codes = $this->access->permissionCodes($user);

        return WorkflowInstance::query()
            ->where('status', WorkflowInstance::IN_PROGRESS)
            ->whereHas('currentStage', fn ($q) => $q->whereIn('permission_code', $codes))
            ->with(['currentStage', 'definition', 'orgUnit', 'initiator'])
            ->orderBy('due_at')->orderBy('id')
            ->limit(500)
            ->get()
            ->filter(fn (WorkflowInstance $i) => $i->initiated_by !== $user->id || $i->definition->allow_self_approval)
            ->filter(fn (WorkflowInstance $i) => $this->access->allows($user, $i->currentStage->permission_code, $i))
            ->values();
    }

    private function nextStage(WorkflowStage $stage): ?WorkflowStage
    {
        return WorkflowStage::query()
            ->where('workflow_definition_id', $stage->workflow_definition_id)
            ->where('sequence', '>', $stage->sequence)
            ->orderBy('sequence')->first();
    }

    private function log(WorkflowInstance $instance, ?WorkflowStage $stage, User $user, ?string $role, string $action, ?string $remarks, ?string $from, string $to): void
    {
        WorkflowAction::create([
            'workflow_instance_id' => $instance->id,
            'workflow_stage_id' => $stage?->id,
            'user_id' => $user->id,
            'role_code' => $role,
            'action' => $action,
            'remarks' => $remarks,
            'from_status' => $from,
            'to_status' => $to,
            'reference' => $instance->reference.'-'.str_pad((string) ($instance->actions()->count() + 1), 2, '0', STR_PAD_LEFT),
        ]);
    }

    /** Dispatch events only once the transaction has committed. */
    private function afterCommit(array $result): WorkflowInstance
    {
        [$instance, $outcome, $action] = $result;
        WorkflowStageChanged::dispatch($instance, $action);
        if ($outcome) {
            WorkflowCompleted::dispatch($instance, $outcome);
        }

        return $instance->fresh(['currentStage', 'definition', 'actions']);
    }
}
