<?php

namespace App\Modules\Registry\Services;

use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Identity\Services\IdentifierService;
use App\Modules\Registry\Models\MatricFormat;
use Illuminate\Validation\ValidationException;

/**
 * Builds matriculation numbers from the institution's format (PRD 6.5). Tokens:
 *   {YEAR} entry year · {YY} two-digit year · {DEPT} department code · {FAC} faculty or college code
 *   {PROG} programme code · {CAT} admission category code · {LEVEL} entry level · {SEQ:n} sequence padded to n
 * The sequence counts within everything before it, so {YEAR}/{DEPT}/{SEQ:4} numbers each department from 0001 every year.
 */
class MatricNumberService
{
    public const DEFAULT_FORMAT = '{YEAR}/{DEPT}/{SEQ:4}';

    public function __construct(private IdentifierService $identifiers) {}

    public function formatFor(AdmissionOffer $offer): string
    {
        $categoryId = $offer->application->exercise->admission_category_id;
        $programmeCategoryId = $offer->programme->programme_category_id;

        $format = MatricFormat::where('active', true)
            ->where(fn ($q) => $q->whereNull('admission_category_id')->orWhere('admission_category_id', $categoryId))
            ->where(fn ($q) => $q->whereNull('programme_category_id')->orWhere('programme_category_id', $programmeCategoryId))
            ->get()
            ->sortByDesc(fn ($f) => ($f->admission_category_id ? 2 : 0) + ($f->programme_category_id ? 1 : 0))
            ->first();

        return $format?->format ?? self::DEFAULT_FORMAT;
    }

    /** Must run inside the caller's transaction: the sequence row stays locked until it commits. */
    public function next(AdmissionOffer $offer): string
    {
        $format = $this->formatFor($offer);
        if (! preg_match('/\{SEQ:(\d)\}/', $format, $m)) {
            throw ValidationException::withMessages(['format' => 'The matric number format needs a {SEQ:n} token.']);
        }
        $tokens = $this->tokens($offer);
        $prefix = strtr(substr($format, 0, strpos($format, $m[0])), $tokens);
        $sequence = $this->identifiers->next('matric:'.$prefix);

        return strtr(str_replace($m[0], str_pad((string) $sequence, (int) $m[1], '0', STR_PAD_LEFT), $format), $tokens);
    }

    public function preview(string $format, AdmissionOffer $offer): string
    {
        $withSequence = preg_replace_callback('/\{SEQ:(\d)\}/', fn ($m) => str_pad('1', (int) $m[1], '0', STR_PAD_LEFT), $format);

        return strtr($withSequence, $this->tokens($offer));
    }

    private function tokens(AdmissionOffer $offer): array
    {
        $offer->loadMissing(['programme.orgUnit.parent', 'application.exercise.session', 'application.exercise.category']);
        $year = substr($offer->application->exercise->session->name, 0, 4);

        return [
            '{YEAR}' => $year, '{YY}' => substr($year, 2), '{DEPT}' => $offer->programme->orgUnit?->code ?? '',
            '{FAC}' => $offer->programme->orgUnit?->parent?->code ?? '', '{PROG}' => $offer->programme->code,
            '{CAT}' => $offer->application->exercise->category->code, '{LEVEL}' => (string) $offer->entry_level,
        ];
    }
}
