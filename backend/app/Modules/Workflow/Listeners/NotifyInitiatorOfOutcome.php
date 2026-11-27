<?php

namespace App\Modules\Workflow\Listeners;

use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Workflow\Events\WorkflowCompleted;

class NotifyInitiatorOfOutcome
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(WorkflowCompleted $event): void
    {
        $instance = $event->instance->loadMissing(['initiator', 'definition']);
        if ($instance->initiator) {
            $this->notifications->send('workflow.completed', $instance->initiator, [
                'reference' => $instance->reference,
                'process' => $instance->definition->name,
                'outcome' => $event->outcome,
            ], '/workflows/'.$instance->uuid);
        }
    }
}
