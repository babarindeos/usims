<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class AdmissionCategory extends Model
{
    use Auditable;

    protected $table = 'admission_categories';

    protected $fillable = ['code', 'name', 'requires_jamb', 'active'];

    protected function casts(): array
    {
        return ['requires_jamb' => 'boolean', 'active' => 'boolean'];
    }
}
