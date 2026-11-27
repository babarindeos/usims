<?php

namespace App\Modules\Identity\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => ['code' => $this->role->code, 'name' => $this->role->name, 'workspace' => $this->role->workspace],
            'scope_type' => $this->scope_type,
            'org_unit' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'code' => $this->orgUnit->code, 'name' => $this->orgUnit->name] : null,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
            'reason' => $this->reason,
        ];
    }
}
