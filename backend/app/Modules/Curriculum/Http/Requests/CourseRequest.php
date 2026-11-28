<?php

namespace App\Modules\Curriculum\Http\Requests;

use App\Modules\Curriculum\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => Course::normaliseCode((string) $this->input('code'))]);
        }
    }

    public function rules(): array
    {
        $course = $this->route('course');
        $req = $course ? 'sometimes' : 'required';

        return [
            'code' => [$req, 'string', 'max:20', 'regex:/^[A-Z]{2,6} \d{3}[A-Z]?$/', Rule::unique('courses', 'code')->ignore($course?->id)],
            'title' => [$req, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'credit_units' => [$req, 'integer', 'between:0,30'],
            'level' => [$req, 'integer', 'between:100,900', 'multiple_of:100'],
            'semester_sequence' => ['nullable', 'integer', 'between:1,4'],
            'org_unit_id' => [$req, 'string', 'exists:org_units,uuid'],
            'course_type' => ['sometimes', Rule::in(Course::TYPES)],
            'has_practical' => ['sometimes', 'boolean'],
            'lecture_hours' => ['nullable', 'integer', 'min:0', 'max:500'],
            'practical_hours' => ['nullable', 'integer', 'min:0', 'max:500'],
            'status' => ['sometimes', Rule::in(['active', 'retired'])],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Use a course code such as CSC 301.'];
    }
}
