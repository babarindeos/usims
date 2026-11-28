<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\AdmissionPayment;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationDocument;
use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Registry and Bursary actions on submitted applications (PRD 5.9). */
class ProcessingService
{
    private const OPEN = ['submitted', 'under_review', 'on_hold', 'recommended'];

    public function __construct(private ApplicationService $applications, private ScreeningEngine $screening, private NotificationService $notifications) {}

    public function rescreen(Application $application, User $by): void
    {
        $this->ensureOpen($application);
        DB::transaction(function () use ($application, $by) {
            $this->screening->screen($application, $by);
            if ($application->status === 'submitted') {
                $this->applications->transition($application, 'under_review', 'Screened by Registry', $by);
            }
        });
    }

    /** Only eligible candidates, or those a person reviewed, can be recommended, and never with an unverified fee. */
    public function recommend(Application $application, User $by, ?string $remarks): void
    {
        $this->ensureOpen($application);
        if ($application->status === 'recommended') {
            return;
        }
        if (! in_array($application->screening_outcome, ['eligible', 'manual_review'], true)) {
            throw ValidationException::withMessages(['application' => 'Screening must find the candidate eligible, or refer them for manual review, before a recommendation.']);
        }
        $fee = $application->payments()->where('purpose', 'application_fee')->first();
        if ($fee && ! $fee->isSettled()) {
            throw ValidationException::withMessages(['application' => 'The application fee has not been verified by Bursary.']);
        }
        if ($application->screening_outcome === 'manual_review' && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Record the outcome of the manual review.']);
        }
        $this->applications->transition($application, 'recommended', $remarks ?? 'Eligible on screening', $by);
    }

    public function hold(Application $application, User $by, string $reason): void
    {
        $this->ensureOpen($application);
        $this->applications->transition($application, 'on_hold', $reason, $by);
    }

    public function reject(Application $application, User $by, string $reason): void
    {
        $this->ensureOpen($application);
        if ($this->onOpenList($application)) {
            throw ValidationException::withMessages(['application' => 'Remove the candidate from the admission list first.']);
        }
        $this->applications->transition($application, 'rejected', $reason, $by);
        if ($user = $application->person->user) {
            $this->notifications->send('application.decision', $user, ['application_no' => $application->application_no, 'outcome' => 'not successful']);
        }
    }

    public function changeProgramme(Application $application, ExerciseProgramme $choice, User $by, string $reason): void
    {
        $this->ensureOpen($application);
        if ($choice->admission_exercise_id !== $application->admission_exercise_id || ! $choice->active) {
            throw ValidationException::withMessages(['exercise_programme_id' => 'Choose a programme offered in this exercise.']);
        }
        if ($this->onOpenList($application)) {
            throw ValidationException::withMessages(['application' => 'Remove the candidate from the admission list first.']);
        }
        DB::transaction(function () use ($application, $choice, $by, $reason) {
            $from = $application->choice?->programme?->code;
            $application->forceFill(['exercise_programme_id' => $choice->id])->save();
            $application->unsetRelation('choice');
            $this->screening->screen($application, $by);
            $this->applications->transition($application, 'under_review', "Programme changed from {$from} to {$choice->programme->code}: {$reason}", $by);
        });
    }

    public function setCorrections(Application $application, bool $open, User $by, string $reason): void
    {
        $this->ensureOpen($application);
        $application->forceFill(['corrections_open' => $open])->save();
        $application->history()->create(['from_status' => $application->status, 'to_status' => $application->status, 'reason' => ($open ? 'Corrections opened: ' : 'Corrections closed: ').$reason, 'user_id' => $by->id]);
    }

    public function reviewDocument(ApplicationDocument $document, User $by, string $status, ?string $remarks): void
    {
        if ($document->superseded_at) {
            throw ValidationException::withMessages(['document' => 'A newer upload replaced this document.']);
        }
        if ($status === 'rejected' && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Say why the document is rejected so the candidate can fix it.']);
        }
        $document->update(['status' => $status, 'reviewed_by' => $by->id, 'reviewed_at' => now(), 'remarks' => $remarks]);
    }

    /** Bursary confirms the money against the bank statement. The person who verifies is recorded. */
    public function reviewPayment(AdmissionPayment $payment, User $by, bool $approve, ?string $remarks): void
    {
        if ($payment->status !== 'submitted') {
            throw ValidationException::withMessages(['payment' => 'Only a submitted payment can be verified or rejected.']);
        }
        if (! $approve && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Say why the payment is rejected.']);
        }
        $payment->update(['status' => $approve ? 'verified' : 'rejected', 'verified_by' => $by->id, 'verified_at' => now(), 'remarks' => $remarks]);
        if ($user = $payment->application->person->user) {
            $this->notifications->send('admission.payment_reviewed', $user, [
                'purpose' => str_replace('_', ' ', $payment->purpose), 'outcome' => $approve ? 'verified' : 'rejected', 'remarks' => $remarks ?? '',
            ]);
        }
    }

    private function onOpenList(Application $application): bool
    {
        return DB::table('admission_list_entries')->join('admission_lists', 'admission_lists.id', '=', 'admission_list_entries.admission_list_id')
            ->where('application_id', $application->id)->whereIn('admission_lists.status', ['draft', 'returned', 'in_review'])->exists();
    }

    private function ensureOpen(Application $application): void
    {
        if (! in_array($application->status, self::OPEN, true)) {
            throw ValidationException::withMessages(['application' => "This application is {$application->status}."]);
        }
    }
}
