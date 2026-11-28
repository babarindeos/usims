<?php

namespace App\Modules\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'staff_no' => $this->staff_no,
            'person' => $this->person ? ['id' => $this->person->uuid, 'full_name' => $this->person->fullName(), 'person_no' => $this->person->person_no] : null,
            'department' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'code' => $this->orgUnit->code, 'name' => $this->orgUnit->name] : null,
            'category' => $this->category,
            'rank' => $this->rank,
            'institutional_email' => $this->institutional_email,
            'employment_status' => $this->employment_status,
            'source' => $this->source,
            'can_manage' => $request->user()?->can('staff.manage', $this->resource) ?? false,
        ];
    }
}
