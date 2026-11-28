<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseProgramme extends Model implements BelongsToOrgUnit
{
    use Auditable;

    protected $fillable = ['admission_exercise_id', 'programme_id', 'entry_level', 'capacity', 'rules', 'active'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'active' => 'boolean'];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->programme?->orgUnit;
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(AdmissionExercise::class, 'admission_exercise_id');
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
