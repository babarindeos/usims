<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class ExamGrade extends Model
{
    use Auditable;

    protected $table = 'exam_grades';

    protected $fillable = ['exam_body_id', 'grade', 'points', 'is_credit', 'sort_order'];

    protected function casts(): array
    {
        return ['is_credit' => 'boolean'];
    }
}
