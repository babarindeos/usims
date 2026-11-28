<?php

namespace App\Modules\Admissions\Events;

use App\Modules\Admissions\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

class ApplicationSubmitted
{
    use Dispatchable;

    public function __construct(public Application $application) {}
}
