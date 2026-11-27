<?php

namespace App\Modules\Institution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrgUnitRequest extends FormRequest
{
    public function rules(): array
    {
        $unit = $this->route('orgUnit');
        $req = $unit ? 'sometimes' : 'required';

        return [
            'parent_id' => ['nullable', 'string', 'exists:org_units,uuid'],
            'type' => [$req, 'string', 'exists:org_unit_types,code'],
            'code' => [$req, 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('org_units', 'code')->where('status', 'active')->ignore($unit?->id)],
            'name' => [$req, 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'head_person_id' => ['nullable', 'string', 'exists:persons,uuid'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'merged', 'dissolved'])],
            'effective_from' => ['sometimes', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
