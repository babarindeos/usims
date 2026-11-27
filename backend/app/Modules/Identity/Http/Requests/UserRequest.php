<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->route('user');

        if ($user) {
            return [
                'name' => ['sometimes', 'string', 'max:255'],
                'status' => ['sometimes', Rule::in(['active', 'suspended', 'disabled'])],
                'mfa_required' => ['sometimes', 'boolean'],
                'password' => ['sometimes', Password::min(10)->mixedCase()->numbers()->uncompromised(0)],
            ];
        }

        return [
            'person_id' => ['required', 'string', 'exists:persons,uuid'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', Password::min(10)->mixedCase()->numbers()],
            'mfa_required' => ['sometimes', 'boolean'],
        ];
    }
}
