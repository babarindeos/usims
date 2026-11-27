<?php

namespace App\Modules\Workflow\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowDefinition extends Model
{
    use Auditable;

    protected $fillable = ['process', 'code', 'name', 'version', 'conditions', 'priority', 'allow_self_approval', 'status'];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'allow_self_approval' => 'boolean'];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('sequence');
    }

    /** All conditions must equal the matching context values. */
    public function matches(array $context): bool
    {
        foreach ($this->conditions ?? [] as $key => $value) {
            if (! array_key_exists($key, $context) || (string) $context[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
