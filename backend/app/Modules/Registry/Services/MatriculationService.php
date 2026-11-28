<?php

namespace App\Modules\Registry\Services;

use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Curriculum\Models\CurriculumVersion;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\IdentifierService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Registry\Events\StudentMatriculated;
use App\Modules\Registry\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Turns a cleared candidate into a student on the same person record (PRD 6.4, 6.5). */
class MatriculationService
{
    public function __construct(
        private IdentifierService $identifiers,
        private MatricNumberService $matric,
        private StudentService $students,
        private NotificationService $notifications,
    ) {}

    /** @return string[] conditions not yet met; empty means the candidate can be matriculated */
    public function unmet(AdmissionOffer $offer): array
    {
        $offer->loadMissing(['application.payments', 'clearance.unit']);
        $unmet = [];
        if ($offer->status !== 'accepted') {
            $unmet[] = 'Offer accepted';
        }
        $fee = $offer->application->payments->firstWhere('purpose', 'acceptance_fee');
        if ($fee && ! $fee->isSettled()) {
            $unmet[] = 'Acceptance fee verified';
        }
        if (! $offer->onboarding_submitted_at) {
            $unmet[] = 'Onboarding submitted';
        }
        foreach ($offer->clearance as $item) {
            if ($item->status !== 'approved') {
                $unmet[] = "Cleared by {$item->unit->name}";
            }
        }
        if (Student::where('admission_offer_id', $offer->id)->exists()) {
            $unmet[] = 'Not already matriculated';
        }

        return $unmet;
    }

    public function matriculate(AdmissionOffer $offer, ?User $by): Student
    {
        if ($unmet = $this->unmet($offer)) {
            throw ValidationException::withMessages(['offer' => array_map(fn ($u) => "Not met: {$u}", $unmet)]);
        }

        $student = DB::transaction(function () use ($offer, $by) {
            AdmissionOffer::whereKey($offer->id)->lockForUpdate()->first();
            $application = $offer->application()->with(['person', 'exercise.session'])->first();
            $session = $application->exercise->session;

            $studentNo = sprintf('STU-%06d', $this->identifiers->next('student'));
            $matricNo = $this->matric->next($offer);

            $student = Student::create([
                'student_no' => $studentNo, 'matric_no' => $matricNo, 'person_id' => $application->person_id,
                'admission_offer_id' => $offer->id, 'programme_id' => $offer->programme_id,
                'admission_category_id' => $application->exercise->admission_category_id, 'admission_session_id' => $session->id,
                'curriculum_version_id' => $this->curriculumFor($offer->programme_id, $session->starts_on),
                'entry_level' => $offer->entry_level, 'current_level' => $offer->entry_level,
                'status' => 'matriculated', 'matriculated_at' => now(), 'matriculated_by' => $by?->id,
            ]);
            $this->identifiers->issue($application->person, 'STU', $studentNo, 'Student record created');
            $this->identifiers->issue($application->person, 'MATRIC', $matricNo, 'Matriculated');
            $this->students->record($student, null, 'matriculated', 'Matriculated from admission '.$offer->admission_no, $by);

            return $student;
        });

        StudentMatriculated::dispatch($student);
        if ($user = $student->person->user) {
            $this->notifications->send('student.matriculated', $user, ['name' => $student->person->first_name, 'matric_no' => $student->matric_no]);
        }

        return $student;
    }

    /** The published curriculum in force for the entry session: the latest whose first cohort is not later. */
    private function curriculumFor(int $programmeId, $sessionStart): ?int
    {
        return CurriculumVersion::where('programme_id', $programmeId)->where('status', 'published')
            ->whereHas('effectiveSession', fn ($q) => $q->where('starts_on', '<=', $sessionStart))
            ->orderByDesc('version')->value('id');
    }
}
