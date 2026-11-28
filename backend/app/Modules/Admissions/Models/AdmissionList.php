<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A batch of recommended candidates that goes through the admission approval workflow. */
class AdmissionList extends Model
{
    use Auditable, HasUuid;

    protected $fillable = ['admission_exercise_id', 'name', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime'];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'returned'], true);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(AdmissionExercise::class, 'admission_exercise_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AdmissionListEntry::class);
    }
}
