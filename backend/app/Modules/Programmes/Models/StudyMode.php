<?php

namespace App\Modules\Programmes\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class StudyMode extends Model
{
    use Auditable;

    protected $table = 'study_modes';

    protected $fillable = ['code', 'name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
