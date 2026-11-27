<?php

namespace App\Modules\Workflow\Events;

use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired after commit when an instance is approved or rejected. Modules listen for their own process. */
class WorkflowCompleted
{
    use Dispatchable;

    public function __construct(public WorkflowInstance $instance, public string $outcome) {}
}
