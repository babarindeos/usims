<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Events\OfferAccepted;
use App\Modules\Admissions\Models\AdmissionList;
use App\Modules\Admissions\Models\AdmissionOffer;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ClearanceUnit;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\IdentifierService;
use App\Modules\Institution\Models\Setting;
use App\Modules\Notifications\Models\NotificationTemplate;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Offers, acceptance and onboarding (PRD 5.11, 6.1, 6.2). */
class OfferService
{
    public const DEFAULT_LETTER = <<<'TXT'
{{ institution }}
Office of the Registrar

{{ date }}

Admission number: {{ admission_no }}
Application number: {{ application_no }}

Dear {{ name }},

OFFER OF PROVISIONAL ADMISSION, {{ session }} ACADEMIC SESSION

We are pleased to offer you provisional admission into {{ programme }} ({{ award }}) in the {{ department }}, {{ category }} category, at {{ level }} level.

Accept this offer on the admission portal by {{ accept_by }}. Acceptance requires the acceptance fee of {{ acceptance_fee }} to be verified, after which you complete onboarding and clearance.

This offer may be withdrawn if any information you supplied is found to be false.

Registrar
TXT;

    public function __construct(private IdentifierService $identifiers, private ApplicationService $applications, private NotificationService $notifications) {}

    public function issue(Application $application, ?AdmissionList $list = null): AdmissionOffer
    {
        $application->loadMissing(['person', 'exercise.session', 'exercise.category', 'choice.programme.orgUnit', 'choice.programme.awardType']);
        if ($application->offer) {
            return $application->offer;
        }
        $exercise = $application->exercise;
        $year = (int) substr($exercise->session->name, 0, 4);
        $admissionNo = sprintf('ADM-%d-%06d', $year, $this->identifiers->next("admission:{$year}"));
        $acceptBy = now()->addDays($exercise->acceptance_days)->toDateString();
        $programme = $application->choice->programme;

        $branding = Setting::where('key', 'branding')->value('value') ?? [];
        $template = Setting::where('key', 'admission_letter_template')->value('value') ?: self::DEFAULT_LETTER;
        $letter = NotificationTemplate::render($template, [
            'institution' => $branding['institution_name'] ?? 'University', 'date' => now()->format('j F Y'),
            'admission_no' => $admissionNo, 'application_no' => $application->application_no, 'name' => trim("{$application->person->first_name} {$application->person->surname}"),
            'session' => $exercise->session->name, 'programme' => $programme->name, 'award' => $programme->awardType?->name,
            'department' => $programme->orgUnit?->name, 'category' => $exercise->category->name, 'level' => $application->choice->entry_level,
            'accept_by' => now()->addDays($exercise->acceptance_days)->format('j F Y'),
            'acceptance_fee' => (float) $exercise->acceptance_fee > 0 ? 'NGN '.number_format((float) $exercise->acceptance_fee, 2) : 'nil',
        ]);

        $offer = AdmissionOffer::create([
            'admission_no' => $admissionNo, 'application_id' => $application->id, 'admission_list_id' => $list?->id,
            'programme_id' => $programme->id, 'entry_level' => $application->choice->entry_level,
            'offered_at' => now(), 'accept_by' => $acceptBy, 'status' => 'offered', 'letter' => $letter,
        ]);
        $this->identifiers->issue($application->person, 'ADM', $admissionNo, 'Admission offered');
        $this->applications->ensurePayment($application, 'acceptance_fee', (float) $exercise->acceptance_fee);
        $this->applications->transition($application, 'offered', "Offer {$admissionNo}".($list ? " on list {$list->name}" : ''), null);

        if ($user = $application->person->user) {
            $this->notifications->send('admission.offered', $user, ['name' => $application->person->first_name, 'programme' => $programme->name, 'admission_no' => $admissionNo, 'accept_by' => $acceptBy], '/applicant');
        }

        return $offer;
    }

    public function accept(AdmissionOffer $offer, User $by): AdmissionOffer
    {
        $this->ensureOffered($offer);

        return DB::transaction(function () use ($offer, $by) {
            $offer->update(['status' => 'accepted', 'responded_at' => now(), 'declaration_accepted' => true]);
            $offer->application->history()->create(['from_status' => 'offered', 'to_status' => 'offered', 'reason' => "Offer {$offer->admission_no} accepted", 'user_id' => $by->id]);
            OfferAccepted::dispatch($offer);

            return $offer;
        });
    }

    public function decline(AdmissionOffer $offer, User $by, string $reason): AdmissionOffer
    {
        $this->ensureOffered($offer);
        $offer->update(['status' => 'declined', 'responded_at' => now(), 'decline_reason' => $reason]);
        $offer->application->history()->create(['from_status' => 'offered', 'to_status' => 'offered', 'reason' => "Offer {$offer->admission_no} declined: {$reason}", 'user_id' => $by->id]);

        return $offer;
    }

    /** Offers not answered by their deadline lapse; run daily by the scheduler. */
    public function expireOverdue(): int
    {
        return AdmissionOffer::where('status', 'offered')->whereDate('accept_by', '<', now()->toDateString())->get()
            ->each->update(['status' => 'expired'])->count();
    }

    /** Onboarding data is the candidate's own; submitting it starts clearance with every active unit. */
    public function submitOnboarding(AdmissionOffer $offer, array $data): AdmissionOffer
    {
        if ($offer->status !== 'accepted') {
            throw ValidationException::withMessages(['offer' => 'Accept the offer before onboarding.']);
        }
        $missing = $this->missingOnboardingDocuments($offer);
        if ($missing) {
            throw ValidationException::withMessages(['documents' => array_map(fn ($n) => "Upload {$n}", $missing)]);
        }

        return DB::transaction(function () use ($offer, $data) {
            $first = ! $offer->onboarding_submitted_at;
            $offer->update(['onboarding' => $data, 'onboarding_submitted_at' => now()]);
            if ($first) {
                $programme = $offer->programme()->with('orgUnit.parent')->first();
                foreach (ClearanceUnit::where('active', true)->orderBy('sort_order')->get() as $unit) {
                    $orgUnit = match ($unit->target) {
                        'department' => $programme->orgUnit,
                        'faculty' => $programme->orgUnit?->parent,
                        default => $unit->orgUnit,
                    };
                    $item = $offer->clearance()->create(['clearance_unit_id' => $unit->id, 'org_unit_id' => $orgUnit?->id, 'status' => 'submitted']);
                    $item->actions()->create(['from_status' => 'pending', 'to_status' => 'submitted', 'remarks' => 'Onboarding submitted']);
                }
            }

            return $offer;
        });
    }

    /** @return string[] names of required onboarding documents not yet uploaded */
    public function missingOnboardingDocuments(AdmissionOffer $offer): array
    {
        $application = $offer->application()->with(['exercise.documents.documentType', 'currentDocuments'])->first();
        $uploaded = $application->currentDocuments->pluck('document_type_id')->all();

        return $application->exercise->documents->where('stage', 'onboarding')->where('required', true)
            ->reject(fn ($d) => in_array($d->document_type_id, $uploaded, true))->map(fn ($d) => $d->documentType->name)->values()->all();
    }

    private function ensureOffered(AdmissionOffer $offer): void
    {
        if ($offer->status !== 'offered') {
            throw ValidationException::withMessages(['offer' => "This offer is {$offer->status}."]);
        }
        if ($offer->accept_by->endOfDay()->isPast()) {
            $offer->update(['status' => 'expired']);
            throw ValidationException::withMessages(['offer' => 'The deadline to respond to this offer has passed.']);
        }
    }
}
