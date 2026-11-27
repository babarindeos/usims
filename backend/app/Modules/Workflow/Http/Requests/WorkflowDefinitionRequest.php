<?php

namespace App\Modules\Workflow\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WorkflowDefinitionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'process' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_.-]*$/', 'unique:workflow_definitions,code'],
            'name' => ['required', 'string', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'conditions.*' => ['scalar'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'allow_self_approval' => ['sometimes', 'boolean'],
            'stages' => ['present', 'array', 'max:20'],
            'stages.*.code' => ['required', 'string', 'max:60', 'distinct'],
            'stages.*.name' => ['required', 'string', 'max:255'],
            'stages.*.permission_code' => ['required', 'string', 'exists:permissions,code'],
            'stages.*.due_in_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'stages.*.can_return' => ['sometimes', 'boolean'],
        ];
    }
}
