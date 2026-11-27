<?php

namespace App\Modules\Institution\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    use Auditable;

    public const CATEGORIES = [
        'resumption', 'registration', 'lectures', 'add_drop', 'continuous_assessment', 'examinations',
        'result_processing', 'department_board', 'faculty_board', 'senate', 'graduation', 'holiday', 'other',
    ];

    protected $fillable = [
        'academic_session_id', 'semester_id', 'category', 'title', 'description', 'starts_on', 'ends_on',
        'audience', 'org_unit_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
