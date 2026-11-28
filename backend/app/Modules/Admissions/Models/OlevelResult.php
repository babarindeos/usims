<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OlevelResult extends Model
{
    use Auditable;

    protected $table = 'olevel_results';

    protected $fillable = ['olevel_sitting_id', 'olevel_subject_id', 'exam_grade_id'];

    public $timestamps = false;

    public function subject(): BelongsTo
    {
        return $this->belongsTo(OlevelSubject::class, 'olevel_subject_id');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(ExamGrade::class, 'exam_grade_id');
    }
}
