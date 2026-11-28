<?php

namespace Tests\Feature\Admissions;

use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Admissions\Services\ScreeningEngine;

class ScreeningTest extends AdmissionsTestCase
{
    private function screen(array $rules, int $score = 250, array $grades = ['ENG' => 'B3', 'MTH' => 'C4', 'PHY' => 'B2', 'CHM' => 'C5', 'BIO' => 'C6']): array
    {
        $exercise = $this->exercise([], $rules);
        $id = $this->completeApplication($this->applicant('s'.random_int(1, 1_000_000).'@example.test'), $exercise, $score, $grades);
        $application = $this->app($id);

        return app(ScreeningEngine::class)->evaluate($application, ExerciseProgramme::find($application->exercise_programme_id));
    }

    public function test_failed_rules_give_ineligible_with_reasons(): void
    {
        $result = $this->screen(['min_utme_score' => 200, 'required_credit_subjects' => ['ENG', 'MTH'], 'min_credit_passes' => 5], 170, ['ENG' => 'B3', 'MTH' => 'D7', 'PHY' => 'B2', 'CHM' => 'C5', 'BIO' => 'C6']);

        $this->assertSame('ineligible', $result['outcome']);
        $failed = collect($result['checks'])->where('passed', false)->pluck('detail')->join(' ');
        $this->assertStringContainsString('Scored 170, minimum 200', $failed);
        $this->assertStringContainsString('No credit in Mathematics', $failed);
        $this->assertStringContainsString('4 credit(s), at least 5', $failed);
    }

    public function test_unverified_items_hold_an_otherwise_eligible_candidate(): void
    {
        $this->assertSame('pending_verification', $this->screen(['min_utme_score' => 180])['outcome']);
    }

    public function test_no_rules_or_manual_flag_goes_to_a_person(): void
    {
        $this->assertSame('manual_review', $this->screen([])['outcome']);
        $this->assertSame('manual_review', $this->screen(['min_utme_score' => 180, 'manual_review' => true, 'require_verified_documents' => false])['outcome']);
    }

    public function test_required_utme_subjects(): void
    {
        $result = $this->screen(['required_utme_subjects' => ['ENG', 'MTH', 'BIO'], 'require_verified_documents' => false]);
        $this->assertSame('ineligible', $result['outcome']);
        $this->assertStringContainsString('Biology', collect($result['checks'])->firstWhere('check', 'UTME subjects')['detail']);
    }
}
