<?php

namespace App\Modules\Workflow\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowInstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'reference' => $this->reference,
            'process' => $this->definition->process,
            'workflow' => $this->definition->name,
            'subject' => ['type' => class_basename($this->subject_type), 'id' => $this->subject_id],
            'org_unit' => $this->orgUnit ? ['id' => $this->orgUnit->uuid, 'name' => $this->orgUnit->name] : null,
            'status' => $this->status,
            'current_stage' => $this->currentStage ? ['code' => $this->currentStage->code, 'name' => $this->currentStage->name] : null,
            'initiated_by' => $this->initiator ? ['name' => $this->initiator->name] : null,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'actions' => $this->whenLoaded('actions', fn () => $this->actions->map(fn ($a) => [
                'reference' => $a->reference,
                'action' => $a->action,
                'stage' => $a->stage?->name,
                'user' => $a->user?->name,
                'role' => $a->role_code,
                'remarks' => $a->remarks,
                'from_status' => $a->from_status,
                'to_status' => $a->to_status,
                'at' => $a->created_at?->toIso8601String(),
            ])),
        ];
    }
}
