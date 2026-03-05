<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'lunch_required' => ['sometimes', 'boolean'],
            'lunch_start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'lunch_end_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'lunch_duration_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'tolerance_minutes' => ['sometimes', 'integer', 'min:0', 'max:60'],
            'overtime_enabled' => ['sometimes', 'boolean'],
            'overtime_min_block_minutes' => ['sometimes', 'integer', 'min:0'],
            'max_daily_overtime_minutes' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
