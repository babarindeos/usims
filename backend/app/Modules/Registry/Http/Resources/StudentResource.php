<?php

namespace App\Modules\Registry\Http\Resources;

use App\Modules\Registry\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->person;

        return [
            'id' => $this->uuid,
            'student_no' => $this->student_no,
            'matric_no' => $this->matric_no,
            'full_name' => $p->fullName(),
            'person' => $this->whenLoaded('statusHistory', fn () => [
                'id' => $p->uuid, 'person_no' => $p->person_no, 'date_of_birth' => $p->date_of_birth?->toDateString(), 'gender' => $p->gender,
                'nationality' => $p->nationality, 'state_of_origin' => $p->state_of_origin, 'lga' => $p->lga, 'email' => $p->email,
                'phone' => $p->phone, 'address' => $p->address,
            ]),
            'programme' => ['id' => $this->programme->uuid, 'code' => $this->programme->code, 'name' => $this->programme->name],
            'department' => $this->programme->orgUnit?->name,
            'faculty' => $this->whenLoaded('statusHistory', fn () => $this->programme->orgUnit?->parent?->name),
            'admission_category' => $this->category?->name,
            'admission_session' => $this->admissionSession?->name,
            'entry_level' => $this->entry_level,
            'current_level' => $this->current_level,
            'status' => $this->status,
            'matriculated_at' => $this->matriculated_at->toIso8601String(),
            'curriculum' => $this->whenLoaded('curriculum', fn () => $this->curriculum ? ['id' => $this->curriculum->uuid, 'version' => $this->curriculum->version, 'title' => $this->curriculum->title] : null),
            'admission_no' => $this->whenLoaded('offer', fn () => $this->offer->admission_no),
            'next_of_kin' => $this->whenLoaded('offer', fn () => $this->offer->application?->next_of_kin),
            'onboarding' => $this->whenLoaded('offer', fn () => $this->offer->onboarding),
            'identifiers' => $this->whenLoaded('statusHistory', fn () => $p->identifiers()->orderBy('issued_at')->get()->map(fn ($i) => [
                'type' => $i->type, 'value' => $i->value, 'issued_at' => $i->issued_at->toIso8601String(), 'superseded_at' => $i->superseded_at?->toIso8601String(),
            ])),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'from' => $h->from_status, 'to' => $h->to_status, 'effective_on' => $h->effective_on->toDateString(), 'reason' => $h->reason, 'by' => $h->user?->name,
            ])),
            'allowed_statuses' => Student::TRANSITIONS[$this->status] ?? [],
            'can_manage' => $request->user()?->can('students.manage', $this->resource) ?? false,
        ];
    }
}
