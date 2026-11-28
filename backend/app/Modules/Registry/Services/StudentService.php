<?php

namespace App\Modules\Registry\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Registry\Events\StudentStatusChanged;
use App\Modules\Registry\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Student status changes are controlled transitions with an append-only history (PRD 7.3). */
class StudentService
{
    public function changeStatus(Student $student, string $to, string $reason, ?string $effectiveOn, User $by): Student
    {
        if (! in_array($to, Student::TRANSITIONS[$student->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "A {$student->status} student cannot be moved to {$to}."]);
        }

        DB::transaction(function () use ($student, $to, $reason, $effectiveOn, $by) {
            $from = $student->status;
            $student->update(['status' => $to]);
            $this->record($student, $from, $to, $reason, $by, $effectiveOn);
        });
        StudentStatusChanged::dispatch($student, $to);

        return $student;
    }

    public function record(Student $student, ?string $from, string $to, string $reason, ?User $by, ?string $effectiveOn = null): void
    {
        $student->statusHistory()->create([
            'from_status' => $from, 'to_status' => $to, 'effective_on' => $effectiveOn ?? now()->toDateString(), 'reason' => $reason, 'user_id' => $by?->id,
        ]);
    }
}
