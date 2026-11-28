<?php

namespace Tests\Feature\Admissions;

use App\Modules\Admissions\Models\AdmissionCategory;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Admissions\Services\OfferService;
use App\Modules\Registry\Models\MatricFormat;
use App\Modules\Registry\Models\Student;
use App\Modules\Registry\Services\MatricNumberService;
use App\Modules\Registry\Services\MatriculationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class AdmissionAccessTest extends AdmissionsTestCase
{
    public function test_applicants_reach_only_their_own_applications(): void
    {
        $exercise = $this->exercise();
        $id = $this->completeApplication($this->applicant(), $exercise);
        $other = $this->applicant('other@example.test');

        $this->actingAs($other)->getJson("/api/v1/applicant/applications/{$id}")->assertNotFound();
        $doc = $this->app($id)->currentDocuments()->first();
        $this->getJson("/api/v1/admission-documents/{$doc->uuid}")->assertNotFound();
        $this->getJson('/api/v1/admission-applications')->assertForbidden();
    }

    public function test_departments_see_applications_for_their_programmes_only(): void
    {
        $exercise = $this->exercise();
        $id = $this->completeApplication($this->applicant(), $exercise);
        $this->postJson("/api/v1/applicant/applications/{$id}/submit")->assertOk();

        $this->actingAs($this->userWith(['admissions.view'], $this->units['mts']));
        $this->assertCount(0, $this->getJson('/api/v1/admission-applications')->json('data'));
        $this->getJson("/api/v1/admission-applications/{$id}")->assertForbidden();

        $this->actingAs($this->userWith(['admissions.view'], $this->units['csc']));
        $this->assertCount(1, $this->getJson('/api/v1/admission-applications?q=APP-2026-000001')->json('data'));
        $this->postJson("/api/v1/admission-applications/{$id}/hold", ['reason' => 'x'])->assertForbidden();
    }

    public function test_corrections_window_reopens_editing_then_closes_on_resubmit(): void
    {
        $exercise = $this->exercise();
        $applicant = $this->applicant();
        $id = $this->completeApplication($applicant, $exercise);
        $this->postJson("/api/v1/applicant/applications/{$id}/submit")->assertOk();

        $this->actingAs($this->userWith(['admissions.view', 'admissions.process']))
            ->postJson("/api/v1/admission-applications/{$id}/open-corrections", ['reason' => 'Wrong JAMB number'])->assertOk()->assertJsonPath('data.corrections_open', true);
        $this->actingAs($applicant)->putJson("/api/v1/applicant/applications/{$id}/details", ['jamb_reg_no' => 'JB12345678'])->assertOk();
        $this->postJson("/api/v1/applicant/applications/{$id}/submit")->assertOk()->assertJsonPath('data.corrections_open', false)->assertJsonPath('data.application_no', 'APP-2026-000001');
        $this->putJson("/api/v1/applicant/applications/{$id}/details", ['jamb_reg_no' => 'JB00000000'])->assertUnprocessable();
    }

    public function test_matric_format_comes_from_configuration_and_sequences_by_prefix(): void
    {
        $exercise = $this->exercise();
        MatricFormat::create(['name' => 'UTME', 'format' => '{YY}{PROG}{SEQ:3}', 'admission_category_id' => AdmissionCategory::where('code', 'UTME')->value('id')]);
        $offers = collect(range(1, 2))->map(function ($i) use ($exercise) {
            $id = $this->completeApplication($this->applicant("m{$i}@example.test"), $exercise);
            $this->postJson("/api/v1/applicant/applications/{$id}/submit")->assertOk();

            return app(OfferService::class)->issue($this->app($id));
        });

        $service = app(MatricNumberService::class);
        $this->assertSame('{YY}{PROG}{SEQ:3}', $service->formatFor($offers[0]));
        $numbers = DB::transaction(fn () => $offers->map(fn (AdmissionOffer $o) => $service->next($o))->all());
        $this->assertSame(['26BSC-CSC001', '26BSC-CSC002'], $numbers);
    }

    public function test_student_status_changes_are_controlled_and_history_is_append_only(): void
    {
        $student = $this->matriculatedStudent();
        $registry = $this->userWith(['students.view', 'students.manage']);
        $this->actingAs($registry);

        $this->postJson("/api/v1/students/{$student->uuid}/status", ['status' => 'graduated', 'reason' => 'x'])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/students/{$student->uuid}/status", ['status' => 'active'])->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/students/{$student->uuid}/status", ['status' => 'active', 'reason' => 'Registered for first semester'])->assertOk()
            ->assertJsonPath('data.status', 'active')->assertJsonCount(2, 'data.status_history');

        $this->expectException(QueryException::class);
        DB::table('student_status_history')->where('student_id', $student->id)->delete();
    }

    private function matriculatedStudent(): Student
    {
        $exercise = $this->exercise(['acceptance_fee' => 0]);
        $applicant = $this->applicant();
        $id = $this->completeApplication($applicant, $exercise);
        $this->postJson("/api/v1/applicant/applications/{$id}/submit")->assertOk();
        $offer = app(OfferService::class)->issue($this->app($id));
        $offer->update(['status' => 'accepted', 'onboarding_submitted_at' => now()]);

        return app(MatriculationService::class)->matriculate($offer->fresh(), null);
    }
}
