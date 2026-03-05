<?php

namespace App\Http\Requests\ScheduleException;

use Illuminate\Foundation\Http\FormRequest;

class BatchScheduleExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'exceptions' => ['required', 'array', 'min:1'],
            'exceptions.*.employee_id' => ['required', 'exists:employees,id'],
            'exceptions.*.date' => ['required', 'date'],
            'exceptions.*.shift_id' => ['nullable', 'exists:shifts,id'],
            'exceptions.*.is_working_day' => ['sometimes', 'boolean'],
            'exceptions.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
