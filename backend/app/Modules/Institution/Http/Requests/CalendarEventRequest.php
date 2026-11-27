<?php

namespace App\Modules\Institution\Http\Requests;

use App\Modules\Institution\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEventRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->route('event') ? 'sometimes' : 'required';

        return [
            'academic_session_id' => [$req, 'integer', 'exists:academic_sessions,id'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'category' => [$req, Rule::in(CalendarEvent::CATEGORIES)],
            'title' => [$req, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'starts_on' => [$req, 'date'],
            'ends_on' => [$req, 'date', 'after_or_equal:starts_on'],
            'audience' => ['sometimes', Rule::in(['all', 'students', 'staff'])],
            'org_unit_id' => ['nullable', 'string', 'exists:org_units,uuid'],
        ];
    }
}
