<?php

namespace App\Http\Requests\EmployeeShift;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeShiftRequest extends FormRequest
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
            'shift_id' => ['sometimes', 'exists:shifts,id'],
            'effective_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:effective_date'],
            'work_days' => ['sometimes', 'array', 'min:1', 'max:7'],
            'work_days.*' => ['integer', 'between:0,6'],
        ];
    }
}
