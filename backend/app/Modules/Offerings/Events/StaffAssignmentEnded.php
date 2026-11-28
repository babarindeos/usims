<?php

namespace App\Modules\Offerings\Events;

use App\Modules\Offerings\Models\CourseOfferingStaff;
use Illuminate\Foundation\Events\Dispatchable;

/** Published for the Moodle integration (M5) and other listeners. */
class StaffAssignmentEnded
{
    use Dispatchable;

    public function __construct(public CourseOfferingStaff $assignment) {}
}
