<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ExerciseProgramme;
use App\Modules\Admissions\Models\OlevelSubject;
use App\Modules\Admissions\Models\ScreeningResult;
use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Evaluates an application against the rules configured on the exercise programme (PRD 5.8).
 * Rules are data; this class only knows how to read them:
 *
 *   min_utme_score            int     UTME aggregate at least this (categories that require JAMB)
 *   required_utme_subjects    codes   each must be among the four UTME subjects
 *   required_credit_subjects  codes   each needs a credit-level grade
 *   min_credit_passes         int     distinct subjects with credit-level grades
 *   max_sittings              int     O'Level sittings allowed
 *   require_verified_documents bool   default true: unverified documents give "pending verification"
 *   manual_review             bool    always send to a person
 *
 * Outcomes: ineligible (a rule failed), manual_review, pending_verification, eligible.
 */
class ScreeningEngine
{
    public const RULE_KEYS = ['min_utme_score', 'required_utme_subjects', 'required_credit_subjects', 'min_credit_passes', 'max_sittings', 'require_verified_documents', 'manual_review'];

    /** @return array{outcome: string, checks: array<int, array{check: string, passed: ?bool, detail: string}>} */
    public function evaluate(Application $application, ExerciseProgramme $choice): array
    {
        $application->loadMissing(['exercise.category', 'sittings.results.grade', 'sittings.results.subject', 'currentDocuments.documentType', 'payments']);
        $rules = $choice->rules ?? [];
        $checks = [];
        $add = function (string $check, ?bool $passed, string $detail) use (&$checks) {
            $checks[] = compact('check', 'passed', 'detail');
        };

        if ($application->exercise->category->requires_jamb && isset($rules['min_utme_score'])) {
            $score = $application->utme_score;
            $add('UTME score', $score !== null && $score >= $rules['min_utme_score'], 'Scored '.($score ?? 'nothing').", minimum {$rules['min_utme_score']}.");
        }
        if (! empty($rules['required_utme_subjects'])) {
            $missing = array_values(array_diff($rules['required_utme_subjects'], $application->utme_subjects ?? []));
            $add('UTME subjects', ! $missing, $missing ? 'Missing '.$this->names($missing).'.' : 'Required UTME subjects present.');
        }

        $sittings = $application->sittings;
        if (isset($rules['max_sittings'])) {
            $add("O'Level sittings", $sittings->count() <= $rules['max_sittings'], "{$sittings->count()} sitting(s), at most {$rules['max_sittings']}.");
        }
        $credits = $sittings->flatMap->results->filter(fn ($r) => $r->grade->is_credit)->map(fn ($r) => $r->subject->code)->unique()->values()->all();
        if (! empty($rules['required_credit_subjects'])) {
            $missing = array_values(array_diff($rules['required_credit_subjects'], $credits));
            $add('Compulsory credits', ! $missing, $missing ? 'No credit in '.$this->names($missing).'.' : 'Credits in all compulsory subjects.');
        }
        if (isset($rules['min_credit_passes'])) {
            $add('Number of credits', count($credits) >= $rules['min_credit_passes'], count($credits)." credit(s), at least {$rules['min_credit_passes']}.");
        }

        if ($rules['require_verified_documents'] ?? true) {
            $unverified = $application->currentDocuments->filter(fn ($d) => $d->documentType->needs_verification && $d->status !== 'verified');
            $rejected = $unverified->where('status', 'rejected');
            if ($rejected->isNotEmpty()) {
                $add('Documents', false, 'Rejected: '.$rejected->map(fn ($d) => $d->documentType->name)->join(', ').'.');
            } else {
                $add('Documents', $unverified->isEmpty() ? true : null, $unverified->isEmpty() ? 'All documents verified.' : 'Awaiting verification: '.$unverified->map(fn ($d) => $d->documentType->name)->join(', ').'.');
            }
        }
        $fee = $application->payments->firstWhere('purpose', 'application_fee');
        if ($fee) {
            $add('Application fee', $fee->isSettled() ? true : ($fee->status === 'rejected' ? false : null), $fee->isSettled() ? 'Verified.' : ($fee->status === 'rejected' ? 'Payment rejected by Bursary.' : 'Awaiting Bursary verification.'));
        }

        $outcome = match (true) {
            collect($checks)->contains(fn ($c) => $c['passed'] === false) => 'ineligible',
            ! array_intersect(array_keys($rules), ['min_utme_score', 'required_utme_subjects', 'required_credit_subjects', 'min_credit_passes', 'max_sittings']) || ! empty($rules['manual_review']) => 'manual_review',
            collect($checks)->contains(fn ($c) => $c['passed'] === null) => 'pending_verification',
            default => 'eligible',
        };
        if ($outcome === 'manual_review') {
            $add('Review', null, empty($rules['manual_review']) ? 'No screening rules are configured for this programme.' : 'This programme requires a manual review.');
        }

        return ['outcome' => $outcome, 'checks' => $checks];
    }

    public function screen(Application $application, ?User $by): ScreeningResult
    {
        $choice = $application->choice;

        return DB::transaction(function () use ($application, $choice, $by) {
            $result = $this->evaluate($application, $choice);
            $application->forceFill(['screening_outcome' => $result['outcome'], 'screened_at' => now()])->save();

            return ScreeningResult::create([
                'application_id' => $application->id, 'exercise_programme_id' => $choice->id, 'outcome' => $result['outcome'],
                'checks' => $result['checks'], 'rules' => $choice->rules ?? [], 'screened_by' => $by?->id,
            ]);
        });
    }

    private function names(array $codes): string
    {
        $names = OlevelSubject::whereIn('code', $codes)->pluck('name', 'code');

        return collect($codes)->map(fn ($c) => $names[$c] ?? $c)->join(', ');
    }
}
