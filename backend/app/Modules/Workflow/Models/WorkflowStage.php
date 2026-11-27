<?php

namespace App\Modules\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStage extends Model
{
    protected $fillable = ['workflow_definition_id', 'sequence', 'code', 'name', 'permission_code', 'due_in_hours', 'can_return'];

    protected function casts(): array
    {
        return ['can_return' => 'boolean'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }
}
