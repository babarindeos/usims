<?php

namespace App\Modules\Workflow\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One recorded step in a workflow; never updated or deleted. */
class WorkflowAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'workflow_instance_id', 'workflow_stage_id', 'user_id', 'role_code', 'action', 'remarks',
        'from_status', 'to_status', 'reference',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }
}
