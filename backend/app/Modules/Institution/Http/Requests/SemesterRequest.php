<?php

namespace App\Modules\Institution\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SemesterRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->route('semester') ? 'sometimes' : 'required';

        return [
            'name' => [$req, 'string', 'max:50'],
            'code' => [$req, 'string', 'max:10'],
            'sequence' => [$req, 'integer', 'min:1', 'max:9'],
            'starts_on' => [$req, 'date'],
            'ends_on' => [$req, 'date', 'after:starts_on'],
            'registration_opens_on' => ['nullable', 'date'],
            'registration_closes_on' => ['nullable', 'date', 'after_or_equal:registration_opens_on'],
            'add_drop_opens_on' => ['nullable', 'date'],
            'add_drop_closes_on' => ['nullable', 'date', 'after_or_equal:add_drop_opens_on'],
            'exams_start_on' => ['nullable', 'date'],
            'exams_end_on' => ['nullable', 'date', 'after_or_equal:exams_start_on'],
            'result_deadline' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['planned', 'active', 'closed'])],
        ];
    }
}
