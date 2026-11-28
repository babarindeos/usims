<?php

namespace App\Modules\Admissions\Events;

use App\Modules\Admissions\Models\AdmissionOffer;
use Illuminate\Foundation\Events\Dispatchable;

class OfferAccepted
{
    use Dispatchable;

    public function __construct(public AdmissionOffer $offer) {}
}
