<?php

namespace App\Modules\Programmes\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    protected $fillable = [
        'code', 'name', 'org_unit_id', 'award_type_id', 'programme_category_id', 'duration_years',
        'min_duration_years', 'max_duration_years', 'capacity', 'accreditation_status', 'accreditation_expires_on',
        'description', 'status',
    ];

    protected function casts(): array
    {
        return ['accreditation_expires_on' => 'date'];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->orgUnit;
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function awardType(): BelongsTo
    {
        return $this->belongsTo(AwardType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCategory::class, 'programme_category_id');
    }

    public function studyModes(): BelongsToMany
    {
        return $this->belongsToMany(StudyMode::class, 'programme_study_modes')->withPivot('duration_years');
    }

    public function entryLevels(): HasMany
    {
        return $this->hasMany(ProgrammeEntryLevel::class)->orderBy('level');
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class)->orderByDesc('version');
    }
}
