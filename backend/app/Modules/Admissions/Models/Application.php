<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Identity\Models\Person;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Scoped to the department of the programme applied for, so departments see their own applicants. */
class Application extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    public const STATUSES = ['draft', 'submitted', 'under_review', 'recommended', 'on_hold', 'rejected', 'offered', 'withdrawn'];

    protected $fillable = [
        'person_id', 'admission_exercise_id', 'exercise_programme_id', 'second_choice_id', 'status', 'next_of_kin',
        'jamb_reg_no', 'jamb_year', 'utme_score', 'utme_subjects', 'corrections_open',
    ];

    protected function casts(): array
    {
        return [
            'next_of_kin' => 'array', 'utme_subjects' => 'array', 'corrections_open' => 'boolean',
            'submitted_at' => 'datetime', 'screened_at' => 'datetime',
        ];
    }

    /** Applicants edit while drafting, or when Registry has opened a correction window. */
    public function isEditableByApplicant(): bool
    {
        return $this->status === 'draft' || ($this->corrections_open && ! in_array($this->status, ['offered', 'withdrawn', 'rejected'], true));
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->choice?->programme?->orgUnit;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(AdmissionExercise::class, 'admission_exercise_id');
    }

    public function choice(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramme::class, 'exercise_programme_id');
    }

    public function secondChoice(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramme::class, 'second_choice_id');
    }

    public function sittings(): HasMany
    {
        return $this->hasMany(OlevelSitting::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(EducationRecord::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function currentDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->whereNull('superseded_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AdmissionPayment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('id');
    }

    public function screenings(): HasMany
    {
        return $this->hasMany(ScreeningResult::class)->orderByDesc('id');
    }

    public function offer(): HasOne
    {
        return $this->hasOne(AdmissionOffer::class);
    }
}
