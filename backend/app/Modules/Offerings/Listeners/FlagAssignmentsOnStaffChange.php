<?php

namespace App\Modules\Offerings\Listeners;

use App\Modules\Offerings\Services\OfferingService;
use App\Modules\Staff\Events\StaffStatusChanged;

class FlagAssignmentsOnStaffChange
{
    public function __construct(private OfferingService $offerings) {}

    public function handle(StaffStatusChanged $event): void
    {
        if ($event->to === 'active') {
            $this->offerings->clearFlagsOf($event->staff);
        } else {
            $this->offerings->flagAssignmentsOf($event->staff, "Staff member is now {$event->to}; reassign this role.");
        }
    }
}
