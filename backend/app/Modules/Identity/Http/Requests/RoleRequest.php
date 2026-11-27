<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'code' => [$role ? 'prohibited' : 'required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')],
            'name' => [$role ? 'sometimes' : 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'workspace' => ['nullable', 'string', 'max:40'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,code'],
        ];
    }
}
