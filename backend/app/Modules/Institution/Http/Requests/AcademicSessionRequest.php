<?php

namespace App\Modules\Institution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicSessionRequest extends FormRequest
{
    public function rules(): array
    {
        $session = $this->route('session');
        $req = $session ? 'sometimes' : 'required';
        $status = ['sometimes', Rule::in(['closed', 'open', 'suspended'])];

        return [
            'name' => [$req, 'string', 'regex:/^\d{4}\/\d{4}$/', Rule::unique('academic_sessions', 'name')->ignore($session?->id)],
            'starts_on' => [$req, 'date'],
            'ends_on' => [$req, 'date', 'after:starts_on'],
            'processes' => ['sometimes', 'array'],
            'processes.application' => $status,
            'processes.admission' => $status,
            'processes.registration' => $status,
            'processes.course_registration' => $status,
            'processes.examination' => $status,
            'processes.result_processing' => $status,
        ];
    }
}
