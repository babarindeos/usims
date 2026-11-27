<?php

namespace App\Modules\Workflow\Events;

use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowStageChanged
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance, public string $action) {}
}
