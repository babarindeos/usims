<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionListEntry extends Model
{
    use Auditable;

    protected $table = 'admission_list_entries';

    protected $fillable = ['admission_list_id', 'application_id', 'exercise_programme_id'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function exerciseProgramme(): BelongsTo
    {
        return $this->belongsTo(ExerciseProgramme::class);
    }
}
