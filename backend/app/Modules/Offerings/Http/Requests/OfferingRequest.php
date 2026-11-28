<?php

namespace App\Modules\Offerings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferingRequest extends FormRequest
{
    public function rules(): array
    {
        $creating = ! $this->route('offering');

        return [
            'course_id' => [$creating ? 'required' : 'prohibited', 'string', 'exists:courses,uuid'],
            'semester_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:semesters,id'],
            'group_code' => [$creating ? 'sometimes' : 'prohibited', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'org_unit_id' => ['sometimes', 'string', 'exists:org_units,uuid'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_opens_on' => ['nullable', 'date'],
            'registration_closes_on' => ['nullable', 'date', 'after_or_equal:registration_opens_on'],
            'delivery_mode' => ['sometimes', Rule::in(['in_person', 'online', 'blended'])],
        ];
    }
}
