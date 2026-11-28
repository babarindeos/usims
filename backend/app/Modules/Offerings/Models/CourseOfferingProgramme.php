<?php

namespace App\Modules\Offerings\Models;

use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOfferingProgramme extends Model
{
    public $timestamps = false;

    protected $fillable = ['course_offering_id', 'programme_id', 'level'];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
}
