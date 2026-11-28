<?php

namespace App\Modules\Offerings\Events;

use App\Modules\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Events\Dispatchable;

/** Published for the Moodle integration (M5) and other listeners. */
class OfferingPublished
{
    use Dispatchable;

    public function __construct(public CourseOffering $offering) {}
}
