<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseDocument extends Model
{
    use Auditable;

    protected $table = 'exercise_documents';

    protected $fillable = ['admission_exercise_id', 'document_type_id', 'stage', 'required'];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
