<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['code', 'module', 'description', 'sensitive'];

    protected function casts(): array
    {
        return ['sensitive' => 'boolean'];
    }
}
