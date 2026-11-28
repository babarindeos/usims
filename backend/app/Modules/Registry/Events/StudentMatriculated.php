<?php

namespace App\Modules\Registry\Events;

use App\Modules\Registry\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;

/** Fees (M4) and Moodle provisioning (M5) start from here. */
class StudentMatriculated
{
    use Dispatchable;

    public function __construct(public Student $student) {}
}
