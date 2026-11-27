<?php

namespace App\Modules\Institution\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicSession extends Model
{
    use Auditable;

    /** Processes that open and close independently per session (PRD 3.2). */
    public const PROCESSES = [
        'application_status', 'admission_status', 'registration_status',
        'course_registration_status', 'examination_status', 'result_processing_status',
    ];

    protected $fillable = [
        'name', 'starts_on', 'ends_on', 'is_current', 'application_status', 'admission_status',
        'registration_status', 'course_registration_status', 'examination_status', 'result_processing_status',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean', 'closed_at' => 'datetime'];
    }

    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class)->orderBy('sequence');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }
}
