<?php

namespace App\Modules\Admissions\Events;

use App\Modules\Admissions\Models\AdmissionOffer;
use Illuminate\Foundation\Events\Dispatchable;

/** Every clearance unit approved the candidate. Registry listens to matriculate where configured. */
class ClearanceCompleted
{
    use Dispatchable;

    public function __construct(public AdmissionOffer $offer) {}
}
