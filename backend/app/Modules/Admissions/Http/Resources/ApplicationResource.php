<?php

namespace App\Modules\Admissions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One shape for the applicant and staff; staff-only parts appear when loaded. */
class ApplicationResource extends JsonResource
{
    public ?array $missing = null;

    public function withMissing(array $missing): static
    {
        $this->missing = $missing;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $p = $this->person;
        $choice = fn ($c) => $c ? [
            'id' => $c->id, 'entry_level' => $c->entry_level,
            'programme' => ['id' => $c->programme->uuid, 'code' => $c->programme->code, 'name' => $c->programme->name, 'department' => $c->programme->orgUnit?->name],
        ] : null;

        return [
            'id' => $this->uuid,
            'application_no' => $this->application_no,
            'status' => $this->status,
            'exercise' => $this->exercise ? [
                'id' => $this->exercise->uuid, 'name' => $this->exercise->name, 'category' => $this->exercise->category?->only(['code', 'name', 'requires_jamb']),
                'session' => $this->exercise->session?->name, 'closes_at' => $this->exercise->closes_at->toIso8601String(), 'instructions' => $this->exercise->instructions,
                'programmes' => $this->when($this->exercise->relationLoaded('programmes'), fn () => $this->exercise->programmes->where('active', true)->values()->map(fn ($c) => $choice($c))),
                'documents' => $this->when($this->exercise->relationLoaded('documents'), fn () => $this->exercise->documents->map(fn ($d) => [
                    'code' => $d->documentType->code, 'name' => $d->documentType->name, 'stage' => $d->stage, 'required' => $d->required, 'max_kb' => $d->documentType->max_kb,
                ])),
            ] : null,
            'applicant' => $p ? [
                'id' => $p->uuid, 'person_no' => $p->person_no, 'full_name' => $p->fullName(), 'title' => $p->title, 'surname' => $p->surname,
                'first_name' => $p->first_name, 'other_names' => $p->other_names, 'date_of_birth' => $p->date_of_birth?->toDateString(),
                'gender' => $p->gender, 'nationality' => $p->nationality, 'state_of_origin' => $p->state_of_origin, 'lga' => $p->lga,
                'email' => $p->email, 'phone' => $p->phone, 'address' => $p->address,
            ] : null,
            'choice' => $choice($this->choice),
            'second_choice' => $choice($this->secondChoice),
            'next_of_kin' => $this->next_of_kin ?? (object) [],
            'jamb' => ['reg_no' => $this->jamb_reg_no, 'year' => $this->jamb_year, 'score' => $this->utme_score, 'subjects' => $this->utme_subjects ?? []],
            'screening_outcome' => $this->screening_outcome,
            'screened_at' => $this->screened_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'corrections_open' => $this->corrections_open,
            'editable' => $this->isEditableByApplicant(),
            'sittings' => $this->whenLoaded('sittings', fn () => $this->sittings->map(fn ($s) => [
                'exam_body' => $s->examBody->code, 'exam_year' => $s->exam_year, 'exam_number' => $s->exam_number,
                'results' => $s->results->map(fn ($r) => ['subject' => $r->subject->code, 'subject_name' => $r->subject->name, 'grade' => $r->grade->grade, 'credit' => $r->grade->is_credit]),
            ])),
            'education' => $this->whenLoaded('education', fn () => $this->education->map->only(['institution', 'qualification', 'course_of_study', 'grade', 'year_obtained'])),
            'documents' => $this->whenLoaded('currentDocuments', fn () => $this->currentDocuments->map(fn ($d) => [
                'id' => $d->uuid, 'type' => ['id' => $d->documentType->id, 'code' => $d->documentType->code, 'name' => $d->documentType->name],
                'original_name' => $d->original_name, 'size' => $d->size, 'status' => $d->status, 'remarks' => $d->remarks,
                'uploaded_at' => $d->created_at->toIso8601String(), 'url' => "/api/v1/admission-documents/{$d->uuid}",
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($pay) => [
                'id' => $pay->uuid, 'purpose' => $pay->purpose, 'amount' => (float) $pay->amount, 'status' => $pay->status,
                'reference' => $pay->reference, 'paid_on' => $pay->paid_on?->toDateString(), 'remarks' => $pay->remarks,
                'evidence_id' => $pay->evidence?->uuid,
            ])),
            'screenings' => $this->whenLoaded('screenings', fn () => $this->screenings->take(5)->map(fn ($s) => [
                'outcome' => $s->outcome, 'checks' => $s->checks, 'at' => $s->created_at->toIso8601String(),
            ])),
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn ($h) => [
                'from' => $h->from_status, 'to' => $h->to_status, 'reason' => $h->reason, 'by' => $h->user?->name, 'at' => $h->created_at->toIso8601String(),
            ])),
            'missing' => $this->when($this->missing !== null, fn () => $this->missing),
            'offer' => $this->whenLoaded('offer', fn () => $this->offer ? new OfferResource($this->offer) : null),
        ];
    }
}
