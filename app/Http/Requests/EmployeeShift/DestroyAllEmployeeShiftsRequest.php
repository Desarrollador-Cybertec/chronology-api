<?php

namespace App\Http\Requests\EmployeeShift;

use Illuminate\Foundation\Http\FormRequest;

class DestroyAllEmployeeShiftsRequest extends FormRequest
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
            'employee_ids' => ['sometimes', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }
}
