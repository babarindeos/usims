<?php

namespace App\Modules\Curriculum\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CurriculumRequest extends FormRequest
{
    public function rules(): array
    {
        $creating = ! $this->route('curriculum');

        return [
            'programme_id' => [$creating ? 'required' : 'prohibited', 'string', 'exists:programmes,uuid'],
            'based_on_id' => [$creating ? 'nullable' : 'prohibited', 'string', 'exists:curriculum_versions,uuid'],
            'title' => ['sometimes', 'string', 'max:255'],
            'effective_session_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:academic_sessions,id'],
            'duration_years' => ['sometimes', 'integer', 'between:1,10'],
            'required_credit_units' => [$creating ? 'required_without:based_on_id' : 'sometimes', 'integer', 'between:1,1000'],
            'min_semester_units' => ['nullable', 'integer', 'between:0,60'],
            'max_semester_units' => ['nullable', 'integer', 'between:1,60', 'gte:min_semester_units'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
