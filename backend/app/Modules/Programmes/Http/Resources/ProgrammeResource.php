<?php

namespace App\Modules\Programmes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'department' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'code' => $this->orgUnit->code, 'name' => $this->orgUnit->name] : null,
            'award_type' => $this->awardType ? ['code' => $this->awardType->code, 'name' => $this->awardType->name] : null,
            'category' => $this->category ? ['code' => $this->category->code, 'name' => $this->category->name] : null,
            'duration_years' => $this->duration_years,
            'min_duration_years' => $this->min_duration_years,
            'max_duration_years' => $this->max_duration_years,
            'capacity' => $this->capacity,
            'accreditation_status' => $this->accreditation_status,
            'accreditation_expires_on' => $this->accreditation_expires_on?->toDateString(),
            'description' => $this->description,
            'status' => $this->status,
            'study_modes' => $this->whenLoaded('studyModes', fn () => $this->studyModes->map(fn ($m) => [
                'code' => $m->code, 'name' => $m->name, 'duration_years' => $m->pivot->duration_years,
            ])),
            'entry_levels' => $this->whenLoaded('entryLevels', fn () => $this->entryLevels->pluck('level')),
            'can_manage' => $request->user()?->can('programmes.manage', $this->resource) ?? false,
        ];
    }
}
