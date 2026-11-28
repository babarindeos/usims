<?php

namespace App\Modules\Admissions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'session' => $this->session ? ['id' => $this->session->id, 'name' => $this->session->name] : null,
            'category' => $this->category ? ['code' => $this->category->code, 'name' => $this->category->name, 'requires_jamb' => $this->category->requires_jamb] : null,
            'opens_at' => $this->opens_at->toIso8601String(),
            'closes_at' => $this->closes_at->toIso8601String(),
            'application_fee' => (float) $this->application_fee,
            'acceptance_fee' => (float) $this->acceptance_fee,
            'capacity' => $this->capacity,
            'acceptance_days' => $this->acceptance_days,
            'instructions' => $this->instructions,
            'status' => $this->status,
            'effective_status' => $this->effectiveStatus(),
            'accepting_applications' => $this->isAcceptingApplications(),
            'applications_count' => $this->whenCounted('applications'),
            'programmes' => $this->whenLoaded('programmes', fn () => $this->programmes->map(fn ($p) => [
                'id' => $p->id,
                'programme' => ['id' => $p->programme->uuid, 'code' => $p->programme->code, 'name' => $p->programme->name, 'department' => $p->programme->orgUnit?->name],
                'entry_level' => $p->entry_level,
                'capacity' => $p->capacity,
                'rules' => $p->rules ?? (object) [],
                'active' => $p->active,
            ])),
            'documents' => $this->whenLoaded('documents', fn () => $this->documents->map(fn ($d) => [
                'id' => $d->id, 'stage' => $d->stage, 'required' => $d->required,
                'type' => ['id' => $d->documentType->id, 'code' => $d->documentType->code, 'name' => $d->documentType->name, 'max_kb' => $d->documentType->max_kb],
            ])),
        ];
    }
}
