<?php

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'mfa_required' => $this->mfa_required,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person->uuid, 'person_no' => $this->person->person_no, 'full_name' => $this->person->fullName(),
            ]),
            'grants' => GrantResource::collection($this->whenLoaded('grants')),
        ];
    }
}
