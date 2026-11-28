<?php

namespace App\Modules\Curriculum\Listeners;

use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Curriculum\Services\CurriculumService;
use App\Modules\Workflow\Events\WorkflowStageChanged;

class SyncCurriculumWithWorkflow
{
    public function __construct(private CurriculumService $curricula) {}

    public function handle(WorkflowStageChanged $event): void
    {
        if ($event->instance->subject_type === (new CurriculumVersion)->getMorphClass()) {
            $this->curricula->syncFromWorkflow($event->instance);
        }
    }
}
