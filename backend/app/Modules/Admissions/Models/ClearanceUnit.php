<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Institution\Models\OrgUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClearanceUnit extends Model
{
    use Auditable;

    protected $table = 'clearance_units';

    protected $fillable = ['code', 'name', 'target', 'org_unit_id', 'instructions', 'sort_order', 'active'];

    public const TARGETS = ['fixed', 'department', 'faculty'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }
}
