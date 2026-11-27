<?php

namespace App\Modules\Institution\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrgUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'parent_id' => $this->parent?->uuid,
            'type' => $this->type ? ['code' => $this->type->code, 'name' => $this->type->name, 'is_academic' => $this->type->is_academic] : null,
            'code' => $this->code,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'depth' => substr_count($this->path, '/') - 2,
            'head' => $this->head ? ['id' => $this->head->uuid, 'full_name' => $this->head->fullName()] : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
        ];
    }
}
