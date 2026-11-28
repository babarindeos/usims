<?php

namespace App\Modules\Curriculum\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseRequisite extends Model
{
    use Auditable;

    protected $fillable = ['course_id', 'required_course_id', 'type', 'requirement'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function requiredCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'required_course_id');
    }
}
