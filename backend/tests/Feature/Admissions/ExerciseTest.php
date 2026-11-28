<?php

namespace Tests\Feature\Admissions;

use App\Modules\Admissions\Models\AdmissionExercise;

/** PRD scenario 1: Registry opens an exercise; applicants see it only while it is open. */
class ExerciseTest extends AdmissionsTestCase
{
    public function test_registry_configures_and_opens_an_exercise(): void
    {
        $this->actingAs($this->userWith(['admissions.configure', 'admissions.view']));
        $id = $this->postJson('/api/v1/admission-exercises', [
            'academic_session_id' => $this->session2026->id, 'category' => 'UTME', 'name' => 'UTME 2026',
            'opens_at' => now()->subHour()->toIso8601String(), 'closes_at' => now()->addWeeks(6)->toIso8601String(),
            'application_fee' => 2000, 'acceptance_fee' => 30000,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->json('data.id');

        $this->postJson("/api/v1/admission-exercises/{$id}/status", ['status' => 'open', 'reason' => 'Go live'])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/admission-exercises/{$id}/programmes", ['programme_id' => $this->csc->uuid, 'entry_level' => 200])->assertJsonValidationErrors('entry_level');
        $this->postJson("/api/v1/admission-exercises/{$id}/programmes", [
            'programme_id' => $this->csc->uuid, 'entry_level' => 100, 'capacity' => 120,
            'rules' => ['min_utme_score' => 200, 'required_credit_subjects' => ['ENG', 'MTH', 'PHY'], 'min_credit_passes' => 5, 'max_sittings' => 2, 'manual_review' => false],
        ])->assertCreated()->assertJsonPath('data.programmes.0.rules.min_utme_score', 200);
        $this->postJson("/api/v1/admission-exercises/{$id}/programmes", ['programme_id' => $this->csc->uuid, 'entry_level' => 100])->assertUnprocessable();
        $this->putJson("/api/v1/admission-exercises/{$id}/documents", ['documents' => [
            ['code' => 'OLEVEL', 'stage' => 'application', 'required' => true], ['code' => 'MEDICAL', 'stage' => 'onboarding', 'required' => true],
        ]])->assertOk()->assertJsonCount(2, 'data.documents');

        $this->getJson('/api/v1/admission-exercises/open')->assertJsonCount(0, 'data'); // still a draft
        $this->postJson("/api/v1/admission-exercises/{$id}/status", ['status' => 'open', 'reason' => 'Go live'])->assertOk()->assertJsonPath('data.effective_status', 'open');
        $this->getJson('/api/v1/admission-exercises/open')->assertJsonCount(1, 'data')->assertJsonPath('data.0.programmes.0.programme.code', 'BSC-CSC');

        $this->postJson("/api/v1/admission-exercises/{$id}/status", ['status' => 'suspended', 'reason' => 'Portal fault'])->assertOk();
        $this->getJson('/api/v1/admission-exercises/open')->assertJsonCount(0, 'data');
    }

    public function test_applications_only_while_open(): void
    {
        $scheduled = $this->exercise(['status' => 'scheduled', 'opens_at' => now()->addDay(), 'name' => 'Later']);
        $user = $this->applicant();
        $this->actingAs($user)->postJson('/api/v1/applicant/applications', ['exercise_id' => $scheduled->uuid])->assertJsonValidationErrors('exercise');

        $this->travel(2)->days();
        $this->assertSame('open', $scheduled->refresh()->effectiveStatus());
        $this->postJson('/api/v1/applicant/applications', ['exercise_id' => $scheduled->uuid])->assertCreated();
    }

    public function test_only_registry_configures(): void
    {
        $this->actingAs($this->userWith(['admissions.view'], $this->units['csc']));
        $this->postJson('/api/v1/admission-exercises', ['name' => 'X'])->assertForbidden();
        $this->postJson('/api/v1/admission-config/categories', ['code' => 'SANDWICH', 'name' => 'Sandwich'])->assertForbidden();

        $this->actingAs($this->userWith(['admissions.configure']));
        $this->postJson('/api/v1/admission-config/categories', ['code' => 'SANDWICH', 'name' => 'Sandwich'])->assertCreated();
        $this->postJson('/api/v1/admission-config/exam-bodies', ['code' => 'GCE', 'name' => 'GCE O Level', 'grades' => [
            ['grade' => 'A', 'points' => 5, 'is_credit' => true], ['grade' => 'C', 'points' => 3, 'is_credit' => true], ['grade' => 'F', 'points' => 0, 'is_credit' => false],
        ]])->assertCreated()->assertJsonCount(3, 'data.grades');
        $this->postJson('/api/v1/admission-config/matric-formats', ['name' => 'No sequence', 'format' => '{YEAR}/{DEPT}'])->assertJsonValidationErrors('format');
        $this->putJson('/api/v1/admission-config/letter-template', ['template' => 'Dear {{ name }}, welcome to {{ programme }}.'])->assertOk();
        $this->assertSame(1, AdmissionExercise::count() + 1);
    }
}
