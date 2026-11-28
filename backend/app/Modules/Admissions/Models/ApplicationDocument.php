<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Support\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    use Auditable, HasUuid;

    protected $fillable = [
        'application_id', 'document_type_id', 'path', 'original_name', 'mime', 'size', 'status',
        'reviewed_by', 'reviewed_at', 'remarks', 'superseded_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
