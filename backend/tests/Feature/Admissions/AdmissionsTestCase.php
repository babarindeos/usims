<?php

namespace Tests\Feature\Admissions;

use App\Modules\Admissions\Models\AdmissionCategory;
use App\Modules\Admissions\Models\AdmissionExercise;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\DocumentType;
use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Admissions\Services\ApplicantAccountService;
use App\Modules\Identity\Models\User;
use App\Modules\Institution\Models\AcademicSession;
use App\Modules\Programmes\Models\Programme;
use Database\Seeders\AdmissionSetupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Academic\AcademicTestCase;

abstract class AdmissionsTestCase extends AcademicTestCase
{
    protected AcademicSession $session2026;

    protected Programme $csc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AdmissionSetupSeeder::class);
        Storage::fake('local');
        $this->session2026 = $this->academicSession('2026/2027');
        $this->csc = $this->programme($this->units['csc']);
        $this->csc->entryLevels()->create(['level' => 100]);
    }

    protected function exercise(array $overrides = [], array $rules = ['min_utme_score' => 180, 'required_credit_subjects' => ['ENG', 'MTH'], 'min_credit_passes' => 5, 'max_sittings' => 2]): AdmissionExercise
    {
        $exercise = AdmissionExercise::create($overrides + [
            'academic_session_id' => $this->session2026->id, 'admission_category_id' => AdmissionCategory::where('code', 'UTME')->value('id'),
            'name' => '2026/2027 UTME admission', 'opens_at' => now()->subDay(), 'closes_at' => now()->addMonth(),
            'application_fee' => 2000, 'acceptance_fee' => 25000, 'status' => 'open',
        ]);
        $exercise->programmes()->create(['programme_id' => $this->csc->id, 'entry_level' => 100, 'rules' => $rules]);
        $exercise->documents()->create(['document_type_id' => DocumentType::where('code', 'OLEVEL')->value('id'), 'stage' => 'application', 'required' => true]);

        return $exercise;
    }

    protected function applicant(string $email = 'ada@example.test'): User
    {
        $user = app(ApplicantAccountService::class)->register([
            'surname' => 'Obi', 'first_name' => 'Ada', 'email' => $email, 'phone' => '0803'.random_int(1000000, 9999999), 'password' => 'a-strong-password',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** Fills every section the way an applicant would through the API. */
    protected function completeApplication(User $user, AdmissionExercise $exercise, int $score = 250, array $grades = ['ENG' => 'B3', 'MTH' => 'C4', 'PHY' => 'B2', 'CHM' => 'C5', 'BIO' => 'C6']): string
    {
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/applicant/applications', ['exercise_id' => $exercise->uuid])->assertCreated()->json('data.id');
        $base = "/api/v1/applicant/applications/{$id}";
        $this->putJson("{$base}/biodata", [
            'surname' => 'Obi', 'first_name' => 'Ada', 'date_of_birth' => '2007-03-01', 'gender' => 'female', 'nationality' => 'Nigerian',
            'state_of_origin' => 'Ogun', 'lga' => 'Abeokuta South', 'phone' => $user->person->phone, 'address' => '1 Example Road',
        ])->assertOk();
        $choice = ExerciseProgramme::where('admission_exercise_id', $exercise->id)->value('id');
        $this->putJson("{$base}/details", [
            'exercise_programme_id' => $choice, 'next_of_kin' => ['name' => 'Ngozi Obi', 'relationship' => 'Mother', 'phone' => '08031111111'],
            'jamb_reg_no' => 'jb'.random_int(10000000, 99999999), 'jamb_year' => 2026, 'utme_score' => $score, 'utme_subjects' => ['ENG', 'MTH', 'PHY', 'CHM'],
        ])->assertOk();
        $this->putJson("{$base}/olevel", ['sittings' => [[
            'exam_body' => 'WAEC', 'exam_year' => 2025, 'exam_number' => '4250101001',
            'results' => collect($grades)->map(fn ($g, $s) => ['subject' => $s, 'grade' => $g])->values()->all(),
        ]]])->assertOk();
        $this->postJson("{$base}/documents", ['document_type' => 'OLEVEL', 'file' => UploadedFile::fake()->create('waec.pdf', 200, 'application/pdf')])->assertCreated();
        $evidence = $this->postJson("{$base}/documents", ['document_type' => 'PAYMENT_EVIDENCE', 'file' => UploadedFile::fake()->create('teller.pdf', 100, 'application/pdf')])
            ->assertCreated()->json('data.documents');
        $evidenceId = collect($evidence)->firstWhere('type.code', 'PAYMENT_EVIDENCE')['id'];
        $this->postJson("{$base}/payments/application_fee", ['reference' => 'TLR'.random_int(100000, 999999), 'paid_on' => now()->toDateString(), 'evidence_document_id' => $evidenceId])->assertOk();

        return $id;
    }

    protected function app(string $uuid): Application
    {
        return Application::where('uuid', $uuid)->firstOrFail();
    }
}
