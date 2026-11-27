<?php

namespace App\Modules\Workflow\Models;

use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Identity\Models\User;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowInstance extends Model implements BelongsToOrgUnit
{
    use HasUuid;

    public const IN_PROGRESS = 'in_progress';

    public const RETURNED = 'returned';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'reference', 'workflow_definition_id', 'subject_type', 'subject_id', 'org_unit_id', 'current_stage_id',
        'status', 'initiated_by', 'due_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->orgUnit;
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'current_stage_id');
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowAction::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::IN_PROGRESS, self::RETURNED], true);
    }
}
