<?php

namespace App\Modules\Staff\Events;

use App\Modules\Staff\Models\Staff;
use Illuminate\Foundation\Events\Dispatchable;

/** Other modules (course allocation, later Moodle roles and access) react to staff leaving or returning. */
class StaffStatusChanged
{
    use Dispatchable;

    public function __construct(public Staff $staff, public string $from, public string $to) {}
}
