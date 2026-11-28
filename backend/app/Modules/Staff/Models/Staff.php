<?php

namespace App\Modules\Staff\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Identity\Models\Person;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    public const CATEGORIES = ['academic', 'administrative', 'technical', 'professional', 'management', 'contract', 'temporary'];

    public const STATUSES = ['active', 'on_leave', 'suspended', 'exited'];

    protected $table = 'staff';

    protected $fillable = ['person_id', 'staff_no', 'org_unit_id', 'category', 'rank', 'institutional_email', 'employment_status', 'source'];

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->orgUnit;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function isActive(): bool
    {
        return $this->employment_status === 'active';
    }
}
