<?php

namespace App\Modules\Registry\Events;

use App\Modules\Registry\Models\Student;
use Illuminate\Foundation\Events\Dispatchable;

class StudentStatusChanged
{
    use Dispatchable;

    public function __construct(public Student $student, public string $to) {}
}
