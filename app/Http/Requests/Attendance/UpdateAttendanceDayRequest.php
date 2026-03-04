<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_check_in' => ['sometimes', 'nullable', 'date'],
            'last_check_out' => ['sometimes', 'nullable', 'date'],
            'worked_minutes' => ['sometimes', 'integer', 'min:0'],
            'overtime_minutes' => ['sometimes', 'integer', 'min:0'],
            'overtime_diurnal_minutes' => ['sometimes', 'integer', 'min:0'],
            'overtime_nocturnal_minutes' => ['sometimes', 'integer', 'min:0'],
            'late_minutes' => ['sometimes', 'integer', 'min:0'],
            'early_departure_minutes' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', 'in:present,absent,incomplete,rest,holiday'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
