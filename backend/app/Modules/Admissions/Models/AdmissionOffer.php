<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionOffer extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    protected $fillable = [
        'admission_no', 'application_id', 'admission_list_id', 'programme_id', 'entry_level', 'offered_at', 'accept_by',
        'status', 'responded_at', 'declaration_accepted', 'decline_reason', 'letter', 'onboarding', 'onboarding_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime', 'accept_by' => 'date', 'responded_at' => 'datetime', 'declaration_accepted' => 'boolean',
            'onboarding' => 'array', 'onboarding_submitted_at' => 'datetime',
        ];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->programme?->orgUnit;
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function clearance(): HasMany
    {
        return $this->hasMany(ClearanceItem::class)->orderBy('id');
    }
}
