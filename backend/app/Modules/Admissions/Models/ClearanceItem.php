<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Scoped to the reviewing unit: a clearance officer sees the items their unit must clear. */
class ClearanceItem extends Model implements BelongsToOrgUnit
{
    use Auditable;

    public const STATUSES = ['pending', 'submitted', 'under_review', 'approved', 'rejected', 'requires_correction'];

    protected $fillable = ['admission_offer_id', 'clearance_unit_id', 'org_unit_id', 'status'];

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->orgUnit;
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ClearanceUnit::class, 'clearance_unit_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AdmissionOffer::class, 'admission_offer_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ClearanceAction::class)->orderBy('id');
    }
}
