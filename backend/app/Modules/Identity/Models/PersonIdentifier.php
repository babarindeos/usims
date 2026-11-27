<?php

namespace App\Modules\Identity\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonIdentifier extends Model
{
    use Auditable;

    protected $fillable = ['person_id', 'type', 'value', 'issued_at', 'superseded_at', 'reason', 'issued_by'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
