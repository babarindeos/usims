<?php

namespace App\Modules\Offerings\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Curriculum\Models\Course;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Institution\Models\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseOffering extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    protected $fillable = [
        'course_id', 'semester_id', 'group_code', 'org_unit_id', 'capacity', 'registration_opens_on',
        'registration_closes_on', 'delivery_mode', 'status', 'lms_shortname', 'published_at', 'published_by',
    ];

    protected function casts(): array
    {
        return ['registration_opens_on' => 'date', 'registration_closes_on' => 'date', 'published_at' => 'datetime'];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->orgUnit;
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(CourseOfferingProgramme::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(CourseOfferingStaff::class);
    }
}
