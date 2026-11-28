<?php

namespace App\Modules\Curriculum\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Institution\Models\AcademicSession;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CurriculumVersion extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    public const EDITABLE = ['draft', 'returned'];

    protected $fillable = [
        'programme_id', 'version', 'title', 'effective_session_id', 'duration_years', 'required_credit_units',
        'min_semester_units', 'max_semester_units', 'status', 'based_on_id', 'created_by', 'notes',
    ];

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'published_at' => 'datetime', 'retired_at' => 'datetime'];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->programme?->orgUnit;
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function effectiveSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'effective_session_id');
    }

    public function basedOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'based_on_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(CurriculumCourse::class)->orderBy('level')->orderBy('semester_sequence');
    }

    public function electiveGroups(): HasMany
    {
        return $this->hasMany(ElectiveGroup::class);
    }
}
