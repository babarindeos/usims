<?php

namespace App\Modules\Curriculum\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumCourse extends Model
{
    use Auditable;

    public const CATEGORIES = ['core', 'elective', 'required_elective', 'general_studies'];

    protected $fillable = ['curriculum_version_id', 'course_id', 'level', 'semester_sequence', 'category', 'credit_units', 'elective_group_id'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function electiveGroup(): BelongsTo
    {
        return $this->belongsTo(ElectiveGroup::class);
    }
}
