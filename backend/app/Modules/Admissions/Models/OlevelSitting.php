<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OlevelSitting extends Model
{
    use Auditable;

    protected $table = 'olevel_sittings';

    protected $fillable = ['application_id', 'exam_body_id', 'exam_year', 'exam_number'];

    public function examBody(): BelongsTo
    {
        return $this->belongsTo(ExamBody::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(OlevelResult::class);
    }
}
