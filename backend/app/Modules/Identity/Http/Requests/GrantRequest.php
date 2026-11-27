<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', 'exists:roles,code'],
            'scope_type' => ['required', Rule::in(['global', 'org_unit'])],
            'org_unit_id' => ['required_if:scope_type,org_unit', 'prohibited_if:scope_type,global', 'nullable', 'string', 'exists:org_units,uuid'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at', 'after:now'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
