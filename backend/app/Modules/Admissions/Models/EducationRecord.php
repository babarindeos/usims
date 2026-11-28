<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class EducationRecord extends Model
{
    use Auditable;

    protected $table = 'education_records';

    protected $fillable = ['application_id', 'institution', 'qualification', 'course_of_study', 'grade', 'year_obtained'];
}
