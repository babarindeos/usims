<?php

namespace App\Modules\Curriculum\Services;

use App\Modules\Curriculum\Models\Course;
use App\Modules\Curriculum\Models\CourseRequisite;
use Illuminate\Validation\ValidationException;

class CourseService
{
    /** Adds a requisite, refusing any prerequisite chain that would loop back to the course itself. */
    public function addRequisite(Course $course, Course $required, string $type, string $requirement = 'pass'): CourseRequisite
    {
        if ($course->is($required)) {
            throw ValidationException::withMessages(['required_course_id' => 'A course cannot require itself.']);
        }
        if ($type === 'prerequisite' && $this->requires($required, $course)) {
            throw ValidationException::withMessages(['required_course_id' => "{$required->code} already depends on {$course->code}; this would create a loop."]);
        }
        if ($course->requisites()->where(['required_course_id' => $required->id, 'type' => $type])->exists()) {
            throw ValidationException::withMessages(['required_course_id' => 'This requisite already exists.']);
        }

        return $course->requisites()->create([
            'required_course_id' => $required->id, 'type' => $type, 'requirement' => $requirement,
        ]);
    }

    /** True when $course needs $target somewhere down its prerequisite chain. */
    public function requires(Course $course, Course $target): bool
    {
        $seen = [];
        $queue = [$course->id];
        while ($queue) {
            $id = array_shift($queue);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $next = CourseRequisite::where('course_id', $id)->where('type', 'prerequisite')->pluck('required_course_id')->all();
            if (in_array($target->id, $next, true)) {
                return true;
            }
            array_push($queue, ...$next);
        }

        return false;
    }
}
