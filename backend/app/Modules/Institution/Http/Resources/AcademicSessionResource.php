<?php

namespace App\Modules\Institution\Http\Resources;

use App\Modules\Institution\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AcademicSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'is_current' => $this->is_current,
            'processes' => collect(AcademicSession::PROCESSES)
                ->mapWithKeys(fn ($p) => [str_replace('_status', '', $p) => $this->{$p}]),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'semesters' => SemesterResource::collection($this->whenLoaded('semesters')),
        ];
    }
}
