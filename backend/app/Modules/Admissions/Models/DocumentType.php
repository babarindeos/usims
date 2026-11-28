<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use Auditable;

    protected $table = 'document_types';

    protected $fillable = ['code', 'name', 'description', 'needs_verification', 'max_kb', 'active'];

    protected function casts(): array
    {
        return ['needs_verification' => 'boolean', 'active' => 'boolean'];
    }
}
