<?php

namespace App\Modules\Institution\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Identity\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgUnit extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    protected $fillable = [
        'parent_id', 'org_unit_type_id', 'code', 'name', 'short_name', 'path', 'head_person_id',
        'email', 'phone', 'status', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(OrgUnitType::class, 'org_unit_type_id');
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'head_person_id');
    }

    /** True when $other is this unit or sits anywhere below it. */
    public function contains(OrgUnit $other): bool
    {
        return str_starts_with($other->path, $this->path);
    }
}
