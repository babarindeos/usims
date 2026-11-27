<?php

namespace App\Modules\Identity\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleGrant extends Model
{
    use Auditable;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_ORG_UNIT = 'org_unit';

    protected $fillable = [
        'user_id', 'role_id', 'scope_type', 'org_unit_id', 'starts_at', 'ends_at',
        'granted_by', 'delegated_from_grant_id', 'reason', 'revoked_at', 'revoked_by',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /** Grants in force right now: started, not ended, not revoked. */
    public function scopeEffective(Builder $query): Builder
    {
        $now = now();

        return $query->whereNull('revoked_at')
            ->where('starts_at', '<=', $now)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }
}
