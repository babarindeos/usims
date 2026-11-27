<?php

namespace App\Modules\Institution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemesterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $date = fn ($v) => $v?->toDateString();

        return [
            'id' => $this->id,
            'academic_session_id' => $this->academic_session_id,
            'name' => $this->name,
            'code' => $this->code,
            'sequence' => $this->sequence,
            'starts_on' => $date($this->starts_on),
            'ends_on' => $date($this->ends_on),
            'registration_opens_on' => $date($this->registration_opens_on),
            'registration_closes_on' => $date($this->registration_closes_on),
            'add_drop_opens_on' => $date($this->add_drop_opens_on),
            'add_drop_closes_on' => $date($this->add_drop_closes_on),
            'exams_start_on' => $date($this->exams_start_on),
            'exams_end_on' => $date($this->exams_end_on),
            'result_deadline' => $date($this->result_deadline),
            'status' => $this->status,
        ];
    }
}
