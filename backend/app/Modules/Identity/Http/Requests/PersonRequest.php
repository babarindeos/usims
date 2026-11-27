<?php

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonRequest extends FormRequest
{
    public function rules(): array
    {
        $person = $this->route('person');
        $required = $person ? 'sometimes' : 'required';

        return [
            'title' => ['nullable', 'string', 'max:20'],
            'surname' => [$required, 'string', 'max:100'],
            'first_name' => [$required, 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'state_of_origin' => ['nullable', 'string', 'max:100'],
            'lga' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('persons', 'email')->ignore($person?->id)->whereNull('merged_into_person_id')],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('persons', 'phone')->ignore($person?->id)->whereNull('merged_into_person_id')],
            'address' => ['nullable', 'string', 'max:1000'],
            'confirm_not_duplicate' => ['sometimes', 'boolean'],
        ];
    }
}
