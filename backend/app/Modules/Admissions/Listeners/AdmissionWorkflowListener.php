<?php

namespace App\Modules\Admissions\Listeners;

use App\Modules\Admissions\Models\AdmissionList;
use App\Modules\Admissions\Services\AdmissionListService;
use App\Modules\Workflow\Events\WorkflowStageChanged;

class AdmissionWorkflowListener
{
    public function __construct(private AdmissionListService $lists) {}

    public function handle(WorkflowStageChanged $event): void
    {
        if ($event->instance->subject_type === (new AdmissionList)->getMorphClass()) {
            $this->lists->syncFromWorkflow($event->instance);
        }
    }
}
