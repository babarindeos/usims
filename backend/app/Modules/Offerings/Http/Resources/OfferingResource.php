<?php

namespace App\Modules\Offerings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $open = in_array($this->status, ['draft', 'published'], true);

        return [
            'id' => $this->uuid,
            'course' => ['id' => $this->course->uuid, 'code' => $this->course->code, 'title' => $this->course->title, 'credit_units' => $this->course->credit_units, 'level' => $this->course->level],
            'semester' => [
                'id' => $this->semester->id, 'name' => $this->semester->name, 'sequence' => $this->semester->sequence,
                'session' => $this->semester->session?->name,
            ],
            'group_code' => $this->group_code,
            'department' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'code' => $this->orgUnit->code, 'name' => $this->orgUnit->name] : null,
            'capacity' => $this->capacity,
            'registration_opens_on' => $this->registration_opens_on?->toDateString(),
            'registration_closes_on' => $this->registration_closes_on?->toDateString(),
            'delivery_mode' => $this->delivery_mode,
            'status' => $this->status,
            'lms_shortname' => $this->lms_shortname,
            'published_at' => $this->published_at?->toIso8601String(),
            'staff_count' => $this->whenCounted('activeStaff'),
            'flagged_count' => $this->whenCounted('flaggedStaff'),
            'programmes' => $this->whenLoaded('programmes', fn () => $this->programmes->map(fn ($p) => [
                'id' => $p->id, 'level' => $p->level,
                'programme' => ['id' => $p->programme->uuid, 'code' => $p->programme->code, 'name' => $p->programme->name],
            ])),
            'staff' => $this->whenLoaded('staffAssignments', fn () => $this->staffAssignments->map(fn ($a) => [
                'id' => $a->id,
                'role' => $a->role,
                'status' => $a->status,
                'effective_from' => $a->effective_from?->toDateString(),
                'effective_to' => $a->effective_to?->toDateString(),
                'reason' => $a->reason,
                'end_reason' => $a->end_reason,
                'flagged_at' => $a->flagged_at?->toIso8601String(),
                'flag_reason' => $a->flag_reason,
                'staff' => [
                    'id' => $a->staff->uuid, 'staff_no' => $a->staff->staff_no, 'full_name' => $a->staff->person?->fullName(),
                    'rank' => $a->staff->rank, 'employment_status' => $a->staff->employment_status,
                ],
            ])),
            'can' => [
                'manage' => $open && ($user?->can('offerings.manage', $this->resource) ?? false),
                'allocate' => $open && ($user?->can('offerings.allocate', $this->resource) ?? false),
            ],
        ];
    }
}
