<?php

namespace App\Modules\Admissions\Models;

use Illuminate\Database\Eloquent\Model;

class ScreeningResult extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['application_id', 'exercise_programme_id', 'outcome', 'checks', 'rules', 'screened_by'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'rules' => 'array'];
    }
}
