<?php

namespace App\Http\Requests\EmployeeShift;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeShiftRequest extends FormRequest
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
            'shift_id' => ['required', 'exists:shifts,id'],
            'effective_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'work_days' => ['sometimes', 'array', 'min:1', 'max:7'],
            'work_days.*' => ['integer', 'between:0,6'],
        ];
    }
}
