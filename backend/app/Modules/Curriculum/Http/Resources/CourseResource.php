<?php

namespace App\Modules\Curriculum\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'credit_units' => $this->credit_units,
            'level' => $this->level,
            'semester_sequence' => $this->semester_sequence,
            'department' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'code' => $this->orgUnit->code, 'name' => $this->orgUnit->name] : null,
            'course_type' => $this->course_type,
            'has_practical' => $this->has_practical,
            'lecture_hours' => $this->lecture_hours,
            'practical_hours' => $this->practical_hours,
            'status' => $this->status,
            'requisites' => $this->whenLoaded('requisites', fn () => $this->requisites->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type,
                'requirement' => $r->requirement,
                'course' => ['id' => $r->requiredCourse->uuid, 'code' => $r->requiredCourse->code, 'title' => $r->requiredCourse->title],
            ])),
            'can_manage' => $request->user()?->can('courses.manage', $this->resource) ?? false,
        ];
    }
}
