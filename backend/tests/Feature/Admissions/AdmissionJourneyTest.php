<?php

namespace Tests\Feature\Admissions;

use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\NotificationDelivery;
use App\Modules\Registry\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

/** PRD acceptance scenarios 2, 3 and 4 end to end through the API. */
class AdmissionJourneyTest extends AdmissionsTestCase
{
    public function test_applicant_registers_and_must_verify_email_before_applying(): void
    {
        Queue::fake();
        $exercise = $this->exercise();
        $this->postJson('/api/v1/applicant/register', [
            'surname' => 'Bello', 'first_name' => 'Musa', 'email' => 'musa@example.test', 'phone' => '08039999999',
            'password' => 'a-strong-password', 'password_confirmation' => 'a-strong-password',
        ])->assertCreated();
        $this->assertTrue(NotificationDelivery::where('template_code', 'applicant.verify_email')->where('recipient', 'musa@example.test')->exists());

        $user = User::where('email', 'musa@example.test')->first();
        $this->actingAs($user)->postJson('/api/v1/applicant/applications', ['exercise_id' => $exercise->uuid])->assertJsonValidationErrors('email');

        $this->get(URL::temporarySignedRoute('applicant.verify', now()->addHour(), ['user' => $user->uuid, 'hash' => sha1($user->email)]))->assertRedirect('/login?verified=1');
        $this->get("/api/v1/applicant/verify/{$user->uuid}/".sha1($user->email))->assertForbidden(); // unsigned
        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->actingAs($user)->postJson('/api/v1/applicant/applications', ['exercise_id' => $exercise->uuid])->assertCreated();
    }

    public function test_full_journey_from_application_to_matriculation(): void
    {
        Queue::fake();
        $csc = $this->units['csc'];
        $exercise = $this->exercise();
        $applicant = $this->applicant();
        $registry = $this->userWith(['admissions.view', 'admissions.process', 'admissions.lists', 'students.view', 'students.matriculate']);
        $bursary = $this->userWith(['admission_payments.verify']);
        $committee = $this->userWith(['admissions.recommend']);
        $vc = $this->userWith(['admissions.approve']);

        // Scenario 2: complete and submit.
        $id = $this->completeApplication($applicant, $exercise);
        $submitted = $this->postJson("/api/v1/applicant/applications/{$id}/submit")->assertOk();
        $submitted->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.application_no', 'APP-2026-000001')
            ->assertJsonPath('data.screening_outcome', 'pending_verification');
        $this->putJson("/api/v1/applicant/applications/{$id}/biodata", ['surname' => 'Changed', 'first_name' => 'Ada', 'date_of_birth' => '2007-03-01', 'gender' => 'female', 'nationality' => 'Nigerian', 'state_of_origin' => 'Ogun', 'lga' => 'X', 'phone' => '1', 'address' => 'Y'])
            ->assertUnprocessable(); // locked after submission

        // Scenario 3: verify, screen, recommend, approve through the workflow.
        $this->actingAs($registry)->postJson("/api/v1/admission-applications/{$id}/recommend")->assertUnprocessable();
        $payment = $this->app($id)->payments()->where('purpose', 'application_fee')->first();
        $this->actingAs($bursary)->postJson("/api/v1/admission-payments/{$payment->uuid}/review", ['approve' => true])->assertOk();
        $this->actingAs($registry);
        $docs = $this->getJson("/api/v1/admission-applications/{$id}")->assertOk()->json('data.documents');
        foreach ($docs as $doc) {
            $this->patchJson("/api/v1/admission-documents/{$doc['id']}", ['status' => 'verified'])->assertOk();
        }
        $this->postJson("/api/v1/admission-applications/{$id}/screen")->assertOk()->assertJsonPath('data.screening_outcome', 'eligible')->assertJsonPath('data.status', 'under_review');
        $this->postJson("/api/v1/admission-applications/{$id}/recommend")->assertOk()->assertJsonPath('data.status', 'recommended');

        $list = $this->postJson('/api/v1/admission-lists', ['exercise_id' => $exercise->uuid, 'name' => 'First list'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admission-lists/{$list}/entries", ['applications' => [$id]])->assertOk()->assertJsonPath('added', 1);
        $workflow = $this->postJson("/api/v1/admission-lists/{$list}/submit")->assertOk()->assertJsonPath('data.status', 'in_review')->json('data.workflow.id');
        $this->actingAs($committee)->postJson("/api/v1/workflows/{$workflow}/actions", ['action' => 'approve'])->assertOk();
        $this->actingAs($vc)->postJson("/api/v1/workflows/{$workflow}/actions", ['action' => 'approve'])->assertOk();

        $offer = AdmissionOffer::sole();
        $this->assertSame('ADM-2026-000001', $offer->admission_no);
        $this->assertStringContainsString('B.Sc. Computer Science', $offer->letter);
        $this->assertSame('offered', $this->app($id)->status);

        // Scenario 4: accept, pay, onboard, clear, matriculate.
        $this->actingAs($applicant);
        $this->postJson("/api/v1/applicant/applications/{$id}/offer/accept")->assertJsonValidationErrors('declaration');
        $this->postJson("/api/v1/applicant/applications/{$id}/offer/accept", ['declaration' => true])->assertOk()->assertJsonPath('data.offer.status', 'accepted');
        $evidence = collect($this->postJson("/api/v1/applicant/applications/{$id}/documents", ['document_type' => 'PAYMENT_EVIDENCE', 'file' => UploadedFile::fake()->create('acc.pdf', 50, 'application/pdf')])->json('data.documents'))->firstWhere('type.code', 'PAYMENT_EVIDENCE')['id'];
        $this->postJson("/api/v1/applicant/applications/{$id}/payments/acceptance_fee", ['reference' => 'ACC-778899', 'paid_on' => now()->toDateString(), 'evidence_document_id' => $evidence])->assertOk();
        $onboarded = $this->putJson("/api/v1/applicant/applications/{$id}/onboarding", [
            'sponsor' => ['name' => 'Ngozi Obi', 'relationship' => 'Mother', 'phone' => '08031111111'],
            'residence' => ['type' => 'off_campus', 'address' => 'Camp, Abeokuta'], 'emergency_contact' => ['name' => 'Ngozi Obi', 'phone' => '08031111111'],
            'medical' => ['blood_group' => 'O+', 'genotype' => 'AA'], 'declaration' => true,
        ])->assertOk();
        $this->assertCount(4, $onboarded->json('data.offer.clearance'));

        $acceptance = $this->app($id)->payments()->where('purpose', 'acceptance_fee')->first();
        $this->actingAs($bursary)->postJson("/api/v1/admission-payments/{$acceptance->uuid}/review", ['approve' => true])->assertOk();

        $this->actingAs($registry)->postJson('/api/v1/matriculations', ['offers' => [$offer->uuid]])->assertOk()
            ->assertJsonPath('data.matriculated', [])->assertJsonCount(1, 'data.skipped');

        // Each unit clears its own item; a department officer only clears for their department.
        $cleaner = $this->userWith(['clearance.review']);
        $deptClearer = $this->userWith(['clearance.review'], $csc);
        $mtsClearer = $this->userWith(['clearance.review'], $this->units['mts']);
        $items = $offer->clearance()->with('unit')->get()->keyBy('unit.code');
        $this->actingAs($mtsClearer)->postJson("/api/v1/clearance-items/{$items['DEPARTMENT']->id}/review", ['status' => 'approved'])->assertForbidden();
        $this->assertCount(0, $this->getJson('/api/v1/clearance-items')->json('data'));
        $this->actingAs($deptClearer);
        $this->assertCount(1, $this->getJson('/api/v1/clearance-items')->json('data'));
        $this->postJson("/api/v1/clearance-items/{$items['DEPARTMENT']->id}/review", ['status' => 'requires_correction'])->assertJsonValidationErrors('remarks');
        $this->postJson("/api/v1/clearance-items/{$items['DEPARTMENT']->id}/review", ['status' => 'requires_correction', 'remarks' => 'Upload a clearer result'])->assertOk();
        $this->actingAs($applicant)->postJson("/api/v1/applicant/applications/{$id}/clearance/{$items['DEPARTMENT']->id}/resubmit", ['remarks' => 'Done'])->assertOk();
        $this->actingAs($deptClearer)->postJson("/api/v1/clearance-items/{$items['DEPARTMENT']->id}/review", ['status' => 'approved'])->assertOk();
        foreach (['REGISTRY', 'BURSARY', 'MEDICAL'] as $code) {
            $this->actingAs($cleaner)->postJson("/api/v1/clearance-items/{$items[$code]->id}/review", ['status' => 'approved', 'remarks' => 'Seen'])->assertOk();
        }

        $done = $this->actingAs($registry)->postJson('/api/v1/matriculations', ['offers' => [$offer->uuid]])->assertOk();
        $done->assertJsonPath('data.matriculated.0.matric_no', '2026/CSC/0001');

        $student = Student::sole();
        $this->assertSame($this->app($id)->person_id, $student->person_id);
        $this->assertSame('matriculated', $student->status);
        $this->assertSame(['APP', 'ADM', 'STU', 'MATRIC'], $student->person->identifiers()->whereIn('type', ['APP', 'ADM', 'STU', 'MATRIC'])->orderBy('id')->pluck('type')->all());
        $this->assertSame(1, $student->statusHistory()->count());
        $this->postJson('/api/v1/matriculations', ['offers' => [$offer->uuid]])->assertJsonPath('data.matriculated', []); // never twice

        $this->getJson("/api/v1/students/{$student->uuid}")->assertOk()->assertJsonPath('data.matric_no', '2026/CSC/0001')
            ->assertJsonPath('data.admission_no', 'ADM-2026-000001');
    }
}
