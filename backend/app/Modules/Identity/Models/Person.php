<?php

namespace App\Modules\Identity\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Support\HasUuid;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Person extends Model
{
    use Auditable, HasFactory, HasUuid;

    protected static function newFactory(): PersonFactory
    {
        return PersonFactory::new();
    }

    protected $table = 'persons';

    protected $fillable = [
        'person_no', 'title', 'surname', 'first_name', 'other_names', 'date_of_birth', 'gender',
        'nationality', 'state_of_origin', 'lga', 'email', 'phone', 'address',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(PersonIdentifier::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function activeIdentifier(string $type): ?PersonIdentifier
    {
        return $this->identifiers()->where('type', $type)->whereNull('superseded_at')->first();
    }

    public function fullName(): string
    {
        return trim("{$this->surname}, {$this->first_name} {$this->other_names}");
    }
}
