<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionPayment extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    protected $fillable = [
        'application_id', 'purpose', 'amount', 'status', 'method', 'reference', 'paid_on', 'evidence_document_id',
        'submitted_at', 'verified_by', 'verified_at', 'remarks',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_on' => 'date', 'submitted_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    /** A fee of zero is satisfied without a payment. */
    public function isSettled(): bool
    {
        return $this->status === 'verified' || (float) $this->amount === 0.0;
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->application?->owningOrgUnit();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'evidence_document_id');
    }
}
