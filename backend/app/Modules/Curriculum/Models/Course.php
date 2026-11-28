<?php

namespace App\Modules\Curriculum\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    public const TYPES = ['departmental', 'faculty', 'university', 'general_studies'];

    protected $fillable = [
        'code', 'title', 'description', 'credit_units', 'level', 'semester_sequence', 'org_unit_id',
        'course_type', 'has_practical', 'lecture_hours', 'practical_hours', 'status',
    ];

    protected function casts(): array
    {
        return ['has_practical' => 'boolean'];
    }

    /** "csc301", "CSC-301" and "CSC 301" all become "CSC 301". */
    public static function normaliseCode(string $code): string
    {
        $clean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return preg_match('/^([A-Z]+)(\d+[A-Z]?)$/', $clean, $m) ? "{$m[1]} {$m[2]}" : $clean;
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->orgUnit;
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function requisites(): HasMany
    {
        return $this->hasMany(CourseRequisite::class);
    }
}
