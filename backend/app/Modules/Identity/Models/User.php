<?php

namespace App\Modules\Identity\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Support\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, HasUuid, Notifiable;

    public const STATUS_ACTIVE = 'active';

    protected $fillable = ['person_id', 'name', 'email', 'password', 'status', 'mfa_required', 'oidc_subject'];

    protected $hidden = ['password', 'remember_token', 'oidc_subject'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'mfa_required' => 'boolean',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function grants(): HasMany
    {
        return $this->hasMany(RoleGrant::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
