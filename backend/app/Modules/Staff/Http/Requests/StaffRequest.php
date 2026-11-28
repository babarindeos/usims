<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('staff_no')) {
            $this->merge(['staff_no' => strtoupper(trim((string) $this->input('staff_no')))]);
        }
    }

    public function rules(): array
    {
        $staff = $this->route('staff');
        $req = $staff ? 'sometimes' : 'required';

        return [
            // A staff record attaches to an existing person, or creates one from the details given.
            'person_id' => [$staff ? 'prohibited' : 'required_without:person', 'string', 'exists:persons,uuid'],
            'person' => [$staff ? 'prohibited' : 'required_without:person_id', 'array'],
            'person.title' => ['nullable', 'string', 'max:20'],
            'person.surname' => ['required_with:person', 'string', 'max:100'],
            'person.first_name' => ['required_with:person', 'string', 'max:100'],
            'person.other_names' => ['nullable', 'string', 'max:150'],
            'person.date_of_birth' => ['nullable', 'date', 'before:today'],
            'person.gender' => ['nullable', Rule::in(['male', 'female'])],
            'person.email' => ['nullable', 'email', 'max:255', Rule::unique('persons', 'email')->whereNull('merged_into_person_id')],
            'person.phone' => ['nullable', 'string', 'max:30', Rule::unique('persons', 'phone')->whereNull('merged_into_person_id')],
            'confirm_not_duplicate' => ['sometimes', 'boolean'],
            'staff_no' => [$req, 'string', 'max:30', 'regex:/^[A-Z0-9\/_-]+$/', Rule::unique('staff', 'staff_no')->ignore($staff?->id)],
            'org_unit_id' => [$req, 'string', 'exists:org_units,uuid'],
            'category' => [$req, Rule::in(Staff::CATEGORIES)],
            'rank' => ['nullable', 'string', 'max:100'],
            'institutional_email' => ['nullable', 'email', 'max:255', Rule::unique('staff', 'institutional_email')->ignore($staff?->id)],
            'employment_status' => ['sometimes', Rule::in(Staff::STATUSES)],
            'reason' => ['required_with:employment_status', 'nullable', 'string', 'max:500'],
        ];
    }
}
