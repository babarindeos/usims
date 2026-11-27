<?php

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'person_no' => $this->person_no,
            'title' => $this->title,
            'surname' => $this->surname,
            'first_name' => $this->first_name,
            'other_names' => $this->other_names,
            'full_name' => $this->fullName(),
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'nationality' => $this->nationality,
            'state_of_origin' => $this->state_of_origin,
            'lga' => $this->lga,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'identifiers' => $this->whenLoaded('identifiers', fn () => $this->identifiers->map(fn ($i) => [
                'type' => $i->type, 'value' => $i->value, 'issued_at' => $i->issued_at?->toIso8601String(),
                'superseded_at' => $i->superseded_at?->toIso8601String(),
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
