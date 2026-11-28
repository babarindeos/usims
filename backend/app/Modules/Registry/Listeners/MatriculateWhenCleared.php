<?php

namespace App\Modules\Registry\Listeners;

use App\Modules\Admissions\Events\ClearanceCompleted;
use App\Modules\Institution\Models\Setting;
use App\Modules\Registry\Services\MatriculationService;

/** When the auto_matriculate setting is on, the last clearance approval matriculates the candidate. */
class MatriculateWhenCleared
{
    public function __construct(private MatriculationService $matriculation) {}

    public function handle(ClearanceCompleted $event): void
    {
        if (Setting::where('key', 'auto_matriculate')->value('value') === true && ! $this->matriculation->unmet($event->offer->fresh())) {
            $this->matriculation->matriculate($event->offer->fresh(), null);
        }
    }
}
