<?php

namespace App\Modules\Registry\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class MatricFormat extends Model
{
    use Auditable;

    protected $fillable = ['name', 'format', 'admission_category_id', 'programme_category_id', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
