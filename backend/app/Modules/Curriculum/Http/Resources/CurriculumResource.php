<?php

namespace App\Modules\Curriculum\Http\Resources;

use App\Modules\Workflow\Models\WorkflowInstance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurriculumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $editable = $this->isEditable();

        return [
            'id' => $this->uuid,
            'version' => $this->version,
            'title' => $this->title,
            'status' => $this->status,
            'programme' => $this->programme ? [
                'id' => $this->programme->uuid, 'code' => $this->programme->code, 'name' => $this->programme->name,
                'department' => $this->programme->orgUnit?->only(['code', 'name']),
            ] : null,
            'effective_session' => $this->effectiveSession ? ['id' => $this->effectiveSession->id, 'name' => $this->effectiveSession->name] : null,
            'duration_years' => $this->duration_years,
            'required_credit_units' => $this->required_credit_units,
            'min_semester_units' => $this->min_semester_units,
            'max_semester_units' => $this->max_semester_units,
            'notes' => $this->notes,
            'based_on' => $this->basedOn ? ['id' => $this->basedOn->uuid, 'version' => $this->basedOn->version] : null,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'retired_at' => $this->retired_at?->toIso8601String(),
            'total_units' => $this->whenLoaded('courses', fn () => (int) $this->courses->sum('credit_units')),
            'elective_groups' => $this->whenLoaded('electiveGroups', fn () => $this->electiveGroups->map(fn ($g) => $g->only(['id', 'code', 'name', 'level', 'semester_sequence', 'min_units', 'max_units']))),
            'courses' => $this->whenLoaded('courses', fn () => $this->courses->sortBy([['level', 'asc'], ['semester_sequence', 'asc'], [fn ($r) => $r->course->code, 'asc']])->values()->map(fn ($r) => [
                'id' => $r->id,
                'course' => ['id' => $r->course->uuid, 'code' => $r->course->code, 'title' => $r->course->title],
                'level' => $r->level,
                'semester_sequence' => $r->semester_sequence,
                'category' => $r->category,
                'credit_units' => $r->credit_units,
                'elective_group_id' => $r->elective_group_id,
            ])),
            'workflow' => $this->when($this->relationLoaded('courses'), fn () => $this->workflow()),
            'can' => [
                'edit' => $editable && ($user?->can('curricula.manage', $this->resource) ?? false),
                'submit' => $editable && ($user?->can('curricula.manage', $this->resource) ?? false),
                'publish' => $this->status === 'approved' && ($user?->can('curricula.publish', $this->resource) ?? false),
                'retire' => $this->status === 'published' && ($user?->can('curricula.publish', $this->resource) ?? false),
                'new_version' => $user?->can('curricula.manage', $this->resource) ?? false,
            ],
        ];
    }

    private function workflow(): ?array
    {
        $instance = WorkflowInstance::where('subject_type', $this->resource->getMorphClass())->where('subject_id', $this->id)
            ->with('currentStage')->latest('id')->first();

        return $instance ? [
            'id' => $instance->uuid, 'reference' => $instance->reference, 'status' => $instance->status,
            'current_stage' => $instance->currentStage?->name,
        ] : null;
    }
}
