<?php

namespace App\Modules\Institution\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class OrgUnitType extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'is_academic', 'sort_order'];

    protected function casts(): array
    {
        return ['is_academic' => 'boolean'];
    }
}
