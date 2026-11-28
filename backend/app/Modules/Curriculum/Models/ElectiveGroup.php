<?php

namespace App\Modules\Curriculum\Models;

use App\Modules\Audit\Models\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectiveGroup extends Model
{
    use Auditable;

    protected $fillable = ['curriculum_version_id', 'code', 'name', 'level', 'semester_sequence', 'min_units', 'max_units'];

    public function version(): BelongsTo
    {
        return $this->belongsTo(CurriculumVersion::class, 'curriculum_version_id');
    }
}
