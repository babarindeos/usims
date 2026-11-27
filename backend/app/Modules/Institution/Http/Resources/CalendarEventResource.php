<?php

namespace App\Modules\Institution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_session_id' => $this->academic_session_id,
            'semester_id' => $this->semester_id,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'starts_on' => $this->starts_on->toDateString(),
            'ends_on' => $this->ends_on->toDateString(),
            'audience' => $this->audience,
            'org_unit' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'name' => $this->orgUnit->name] : null,
        ];
    }
}
