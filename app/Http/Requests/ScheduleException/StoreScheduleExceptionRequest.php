<?php

namespace App\Http\Requests\ScheduleException;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleExceptionRequest extends FormRequest
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
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'is_working_day' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
