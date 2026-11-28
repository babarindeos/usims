<?php

namespace App\Modules\Offerings\Models;

use App\Modules\Audit\Models\Auditable;
use App\Modules\Staff\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseOfferingStaff extends Model
{
    use Auditable;

    public const ROLES = ['lecturer', 'coordinator', 'practical_instructor', 'teaching_assistant', 'examiner', 'moderator'];

    protected $table = 'course_offering_staff';

    protected $fillable = [
        'course_offering_id', 'staff_id', 'role', 'effective_from', 'effective_to', 'status', 'assigned_by', 'reason',
        'flagged_at', 'flag_reason', 'ended_by', 'end_reason',
    ];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'flagged_at' => 'datetime'];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
