<?php

namespace App\Modules\Institution\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Semester extends Model
{
    use Auditable;

    protected $fillable = [
        'academic_session_id', 'name', 'code', 'sequence', 'starts_on', 'ends_on',
        'registration_opens_on', 'registration_closes_on', 'add_drop_opens_on', 'add_drop_closes_on',
        'exams_start_on', 'exams_end_on', 'result_deadline', 'status',
    ];

    protected function casts(): array
    {
        $dates = ['starts_on', 'ends_on', 'registration_opens_on', 'registration_closes_on', 'add_drop_opens_on',
            'add_drop_closes_on', 'exams_start_on', 'exams_end_on', 'result_deadline'];

        return array_fill_keys($dates, 'date');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'academic_session_id');
    }
}
