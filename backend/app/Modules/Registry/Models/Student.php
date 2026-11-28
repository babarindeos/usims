<?php

namespace App\Modules\Registry\Models;

use App\Modules\Admissions\Models\AdmissionCategory;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Audit\Models\Auditable;
use App\Modules\Core\Contracts\BelongsToOrgUnit;
use App\Modules\Core\Support\HasUuid;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Identity\Models\Person;
use App\Modules\Institution\Models\AcademicSession;
use App\Modules\Institution\Models\OrgUnit;
use App\Modules\Programmes\Models\Programme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model implements BelongsToOrgUnit
{
    use Auditable, HasUuid;

    public const STATUSES = ['matriculated', 'active', 'deferred', 'suspended', 'withdrawn', 'expelled', 'graduated', 'deceased'];

    /** Statuses a Registry officer may move a student to from each status. Graduation comes with M7. */
    public const TRANSITIONS = [
        'matriculated' => ['active', 'deferred', 'withdrawn', 'deceased'],
        'active' => ['deferred', 'suspended', 'withdrawn', 'expelled', 'deceased'],
        'deferred' => ['active', 'withdrawn', 'deceased'],
        'suspended' => ['active', 'withdrawn', 'expelled', 'deceased'],
        'withdrawn' => [], 'expelled' => [], 'graduated' => [], 'deceased' => [],
    ];

    protected $fillable = [
        'student_no', 'matric_no', 'person_id', 'admission_offer_id', 'programme_id', 'admission_category_id',
        'admission_session_id', 'curriculum_version_id', 'entry_level', 'current_level', 'status', 'matriculated_at', 'matriculated_by',
    ];

    protected function casts(): array
    {
        return ['matriculated_at' => 'datetime'];
    }

    public function owningOrgUnit(): ?OrgUnit
    {
        return $this->programme?->orgUnit;
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AdmissionCategory::class, 'admission_category_id');
    }

    public function admissionSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'admission_session_id');
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(AdmissionOffer::class, 'admission_offer_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StudentStatusHistory::class)->orderBy('id');
    }
}
