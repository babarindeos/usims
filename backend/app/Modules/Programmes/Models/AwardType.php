<?php

namespace App\Modules\Programmes\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class AwardType extends Model
{
    use Auditable;

    protected $table = 'award_types';

    protected $fillable = ['code', 'name', 'active', 'level'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
