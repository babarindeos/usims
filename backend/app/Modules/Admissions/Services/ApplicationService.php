<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Events\ApplicationSubmitted;
use App\Modules\Admissions\Models\AdmissionExercise;
use App\Modules\Admissions\Models\AdmissionPayment;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationDocument;
use App\Modules\Admissions\Models\DocumentType;
use App\Modules\Admissions\Models\ExamBody;
use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Admissions\Models\OlevelSubject;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\IdentifierService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The applicant's side of an application (PRD 5.3 to 5.7). */
class ApplicationService
{
    public function __construct(private IdentifierService $identifiers) {}

    public function start(User $user, AdmissionExercise $exercise): Application
    {
        if (! $exercise->isAcceptingApplications()) {
            throw ValidationException::withMessages(['exercise' => 'This admission exercise is not open for applications.']);
        }
        $existing = Application::where(['person_id' => $user->person_id, 'admission_exercise_id' => $exercise->id])->first();
        if ($existing) {
            return $existing;
        }
        if ($exercise->capacity && $exercise->applications()->whereNot('status', 'draft')->count() >= $exercise->capacity) {
            throw ValidationException::withMessages(['exercise' => 'This exercise has reached the number of applications it can take.']);
        }

        return DB::transaction(function () use ($user, $exercise) {
            $application = Application::create(['person_id' => $user->person_id, 'admission_exercise_id' => $exercise->id, 'status' => 'draft']);
            $this->record($application, null, 'draft', 'Application started', $user);

            return $application;
        });
    }

    public function updateBiodata(Application $application, array $data): void
    {
        $this->ensureEditable($application);
        $application->person->update($data);
    }

    public function updateDetails(Application $application, array $data): Application
    {
        $this->ensureEditable($application);
        foreach (['exercise_programme_id', 'second_choice_id'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $choice = ExerciseProgramme::find($data[$key]);
                if (! $choice || $choice->admission_exercise_id !== $application->admission_exercise_id || ! $choice->active) {
                    throw ValidationException::withMessages([$key => 'Choose a programme offered in this exercise.']);
                }
            }
        }
        if (isset($data['exercise_programme_id'], $data['second_choice_id']) && $data['exercise_programme_id'] === $data['second_choice_id']) {
            throw ValidationException::withMessages(['second_choice_id' => 'The second choice must differ from the first.']);
        }
        $application->update($data);

        return $application;
    }

    /** @param array<int, array{exam_body: string, exam_year: int, exam_number: string, results: array<int, array{subject: string, grade: string}>}> $sittings */
    public function replaceSittings(Application $application, array $sittings): void
    {
        $this->ensureEditable($application);
        DB::transaction(function () use ($application, $sittings) {
            $application->sittings()->delete();
            foreach ($sittings as $i => $s) {
                $body = ExamBody::where('code', $s['exam_body'])->where('active', true)->first()
                    ?? throw ValidationException::withMessages(["sittings.{$i}.exam_body" => 'Unknown examination body.']);
                $sitting = $application->sittings()->create(['exam_body_id' => $body->id, 'exam_year' => $s['exam_year'], 'exam_number' => strtoupper($s['exam_number'])]);
                $grades = $body->grades()->pluck('id', 'grade');
                $subjects = OlevelSubject::where('active', true)->pluck('id', 'code');
                $seen = [];
                foreach ($s['results'] as $j => $r) {
                    $subjectId = $subjects[$r['subject']] ?? throw ValidationException::withMessages(["sittings.{$i}.results.{$j}.subject" => 'Unknown subject.']);
                    $gradeId = $grades[strtoupper($r['grade'])] ?? throw ValidationException::withMessages(["sittings.{$i}.results.{$j}.grade" => "{$r['grade']} is not a {$body->code} grade."]);
                    if (isset($seen[$subjectId])) {
                        throw ValidationException::withMessages(["sittings.{$i}.results.{$j}.subject" => 'Each subject once per sitting.']);
                    }
                    $seen[$subjectId] = true;
                    $sitting->results()->create(['olevel_subject_id' => $subjectId, 'exam_grade_id' => $gradeId]);
                }
            }
        });
    }

    public function replaceEducation(Application $application, array $records): void
    {
        $this->ensureEditable($application);
        DB::transaction(function () use ($application, $records) {
            $application->education()->delete();
            foreach ($records as $r) {
                $application->education()->create($r);
            }
        });
    }

    /** A new upload of the same type supersedes the old one; the old file and its review stay on record. */
    public function upload(Application $application, DocumentType $type, UploadedFile $file, bool $staff = false): ApplicationDocument
    {
        if (! $staff) {
            $this->ensureEditable($application, allowOffered: true);
        }
        if ($file->getSize() > $type->max_kb * 1024) {
            throw ValidationException::withMessages(['file' => "{$type->name} must be at most {$type->max_kb} KB."]);
        }

        return DB::transaction(function () use ($application, $type, $file) {
            $application->documents()->where('document_type_id', $type->id)->whereNull('superseded_at')->get()
                ->each->update(['superseded_at' => now()]);
            $path = $file->store("admissions/{$application->uuid}", 'local');

            return $application->documents()->create([
                'document_type_id' => $type->id, 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime' => $file->getMimeType() ?? 'application/octet-stream', 'size' => $file->getSize(),
                'status' => $type->needs_verification ? 'pending' : 'verified',
            ]);
        });
    }

    /** The candidate records a bank payment; it counts only after Bursary verifies it. */
    public function recordPayment(AdmissionPayment $payment, array $data, ?ApplicationDocument $evidence): AdmissionPayment
    {
        if (in_array($payment->status, ['verified', 'submitted'], true)) {
            throw ValidationException::withMessages(['payment' => $payment->status === 'verified' ? 'This fee is already verified.' : 'This payment is awaiting verification by Bursary.']);
        }
        if ($evidence && $evidence->application_id !== $payment->application_id) {
            throw ValidationException::withMessages(['evidence_document_id' => 'Upload the payment evidence to this application first.']);
        }
        $clash = AdmissionPayment::where('method', 'bank')->where('reference', $data['reference'])->whereNot('status', 'rejected')->whereKeyNot($payment->id)->exists();
        if ($clash) {
            throw ValidationException::withMessages(['reference' => 'This payment reference has already been used.']);
        }
        $payment->update([
            'method' => 'bank', 'reference' => $data['reference'], 'paid_on' => $data['paid_on'], 'evidence_document_id' => $evidence?->id,
            'status' => 'submitted', 'submitted_at' => now(), 'remarks' => null,
        ]);

        return $payment;
    }

    /** Ensures the fee rows exist; zero fees are created as verified so nothing waits on them. */
    public function ensurePayment(Application $application, string $purpose, float $amount): AdmissionPayment
    {
        return $application->payments()->firstOrCreate(['purpose' => $purpose], [
            'amount' => $amount, 'status' => $amount > 0 ? 'awaiting_payment' : 'verified',
        ]);
    }

    /** Lists what still blocks submission; empty means ready. */
    public function missing(Application $application): array
    {
        $application->loadMissing(['person', 'exercise.category', 'exercise.documents', 'sittings.results', 'currentDocuments', 'payments']);
        $p = $application->person;
        $missing = [];
        foreach (['date_of_birth' => 'date of birth', 'gender' => 'gender', 'nationality' => 'nationality', 'state_of_origin' => 'state of origin', 'lga' => 'local government area', 'phone' => 'phone number', 'address' => 'residential address'] as $field => $label) {
            if (blank($p->{$field})) {
                $missing[] = "Biodata: {$label}";
            }
        }
        $kin = $application->next_of_kin ?? [];
        if (blank($kin['name'] ?? null) || blank($kin['phone'] ?? null) || blank($kin['relationship'] ?? null)) {
            $missing[] = 'Next of kin name, relationship and phone';
        }
        if (! $application->exercise_programme_id) {
            $missing[] = 'Programme choice';
        }
        if ($application->exercise->category->requires_jamb && (! $application->jamb_reg_no || $application->utme_score === null || count($application->utme_subjects ?? []) < 4)) {
            $missing[] = 'JAMB registration number, UTME score and four UTME subjects';
        }
        if ($application->sittings->isEmpty()) {
            $missing[] = "At least one O'Level sitting";
        }
        $uploaded = $application->currentDocuments->pluck('document_type_id')->all();
        foreach ($application->exercise->documents->where('stage', 'application')->where('required', true) as $req) {
            if (! in_array($req->document_type_id, $uploaded, true)) {
                $missing[] = 'Document: '.$req->documentType->name;
            }
        }
        $fee = $application->payments->firstWhere('purpose', 'application_fee');
        if ((float) $application->exercise->application_fee > 0 && (! $fee || ! in_array($fee->status, ['submitted', 'verified'], true))) {
            $missing[] = 'Application fee payment';
        }

        return $missing;
    }

    public function submit(Application $application, User $user): Application
    {
        if (! $application->isEditableByApplicant()) {
            throw ValidationException::withMessages(['application' => 'This application has already been submitted.']);
        }
        if (! $application->exercise->isAcceptingApplications() && $application->status === 'draft') {
            throw ValidationException::withMessages(['application' => 'This admission exercise has closed.']);
        }
        if ($missing = $this->missing($application)) {
            throw ValidationException::withMessages(['application' => $missing]);
        }

        return DB::transaction(function () use ($application, $user) {
            if ($application->status === 'draft') {
                $year = (int) substr($application->exercise->session->name, 0, 4);
                $no = sprintf('APP-%d-%06d', $year, $this->identifiers->next("application:{$year}"));
                $application->forceFill(['application_no' => $no, 'submitted_at' => now()])->save();
                $this->identifiers->issue($application->person, 'APP', $no, 'Application submitted');
                $this->transition($application, 'submitted', 'Submitted by applicant', $user);
            } else {
                // A correction window closes once the applicant resubmits.
                $application->forceFill(['corrections_open' => false])->save();
                $this->record($application, $application->status, $application->status, 'Corrections submitted', $user);
            }
            ApplicationSubmitted::dispatch($application);

            return $application;
        });
    }

    public function transition(Application $application, string $to, ?string $reason, ?User $user): void
    {
        $from = $application->status;
        $application->forceFill(['status' => $to])->save();
        $this->record($application, $from, $to, $reason, $user);
    }

    private function record(Application $application, ?string $from, string $to, ?string $reason, ?User $user): void
    {
        $application->history()->create(['from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'user_id' => $user?->id]);
    }

    private function ensureEditable(Application $application, bool $allowOffered = false): void
    {
        if ($application->isEditableByApplicant()) {
            return;
        }
        if ($allowOffered && $application->status === 'offered') {
            return; // onboarding documents
        }
        throw ValidationException::withMessages(['application' => 'This application is locked. Registry can open it for corrections.']);
    }
}
