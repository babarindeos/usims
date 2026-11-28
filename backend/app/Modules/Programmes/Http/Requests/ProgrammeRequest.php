<?php

namespace App\Modules\Programmes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgrammeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $programme = $this->route('programme');
        $req = $programme ? 'sometimes' : 'required';

        return [
            'code' => [$req, 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('programmes', 'code')->ignore($programme?->id)],
            'name' => [$req, 'string', 'max:255'],
            'org_unit_id' => [$req, 'string', 'exists:org_units,uuid'],
            'award_type' => [$req, 'string', Rule::exists('award_types', 'code')->where('active', true)],
            'category' => [$req, 'string', Rule::exists('programme_categories', 'code')->where('active', true)],
            'duration_years' => [$req, 'integer', 'between:1,10'],
            'min_duration_years' => [$req, 'integer', 'between:1,10', 'lte:duration_years'],
            'max_duration_years' => [$req, 'integer', 'between:1,15', 'gte:duration_years'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'accreditation_status' => ['nullable', Rule::in(['full', 'interim', 'denied', 'pending', 'not_required'])],
            'accreditation_expires_on' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'phased_out'])],
            'study_modes' => ['sometimes', 'array', 'min:1'],
            'study_modes.*.code' => ['required', 'string', Rule::exists('study_modes', 'code')->where('active', true)],
            'study_modes.*.duration_years' => ['nullable', 'integer', 'between:1,15'],
            'entry_levels' => ['sometimes', 'array', 'min:1'],
            'entry_levels.*' => ['integer', 'between:100,900', 'multiple_of:100'],
        ];
    }
}
