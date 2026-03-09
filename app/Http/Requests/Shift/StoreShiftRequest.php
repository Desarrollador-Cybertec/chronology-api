<?php

namespace App\Http\Requests\Shift;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
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
            'breaks' => ['sometimes', 'array'],
            'breaks.*.type' => ['required', 'string', 'max:50'],
            'breaks.*.start_time' => ['required', 'date_format:H:i'],
            'breaks.*.end_time' => ['required', 'date_format:H:i'],
            'breaks.*.duration_minutes' => ['required', 'integer', 'min:1', 'max:120'],
            'breaks.*.position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
