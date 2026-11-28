<?php

namespace App\Modules\Admissions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public ?array $extra = null;

    /** Staff views add matriculation readiness and the student record. */
    public function withExtra(array $extra): static
    {
        $this->extra = $extra;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'admission_no' => $this->admission_no,
            'programme' => $this->programme ? ['id' => $this->programme->uuid, 'code' => $this->programme->code, 'name' => $this->programme->name] : null,
            'entry_level' => $this->entry_level,
            'offered_at' => $this->offered_at->toIso8601String(),
            'accept_by' => $this->accept_by->toDateString(),
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'letter' => $this->letter,
            'onboarding' => $this->onboarding ?? (object) [],
            'onboarding_submitted_at' => $this->onboarding_submitted_at?->toIso8601String(),
            'clearance' => $this->whenLoaded('clearance', fn () => $this->clearance->map(fn ($c) => [
                'id' => $c->id, 'unit' => $c->unit->name, 'instructions' => $c->unit->instructions, 'status' => $c->status,
                'reviewer_unit' => $c->orgUnit?->name,
                'actions' => $c->relationLoaded('actions') ? $c->actions->map(fn ($a) => ['to' => $a->to_status, 'remarks' => $a->remarks, 'by' => $a->user?->name, 'at' => $a->created_at->toIso8601String()]) : [],
            ])),
            $this->mergeWhen($this->extra !== null, fn () => $this->extra),
        ];
    }
}
