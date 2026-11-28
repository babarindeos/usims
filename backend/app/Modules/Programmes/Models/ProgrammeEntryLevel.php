<?php

namespace App\Modules\Programmes\Models;

use Illuminate\Database\Eloquent\Model;

class ProgrammeEntryLevel extends Model
{
    public $timestamps = false;

    protected $fillable = ['programme_id', 'level'];
}
