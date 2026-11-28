<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Institution\Models\AcademicSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionExercise extends Model
{
    use Auditable, HasUuid;

    public const STATUSES = ['draft', 'scheduled', 'open', 'suspended', 'closed'];

    protected $fillable = [
        'academic_session_id', 'admission_category_id', 'name', 'opens_at', 'closes_at', 'application_fee',
        'acceptance_fee', 'capacity', 'acceptance_days', 'instructions', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'application_fee' => 'decimal:2', 'acceptance_fee' => 'decimal:2'];
    }

    /** Open now: either opened by hand or scheduled and inside its dates (PRD scenario 1). */
    public function isAcceptingApplications(): bool
    {
        return in_array($this->status, ['open', 'scheduled'], true) && now()->between($this->opens_at, $this->closes_at);
    }

    /** What applicants and staff see, with "scheduled" resolved against the clock. */
    public function effectiveStatus(): string
    {
        if (in_array($this->status, ['open', 'scheduled'], true)) {
            return match (true) {
                now()->lt($this->opens_at) => 'scheduled',
                now()->gt($this->closes_at) => 'closed',
                default => 'open',
            };
        }

        return $this->status;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdmissionCategory::class, 'admission_category_id');
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(ExerciseProgramme::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ExerciseDocument::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
