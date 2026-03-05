<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'crosses_midnight' => $this->crosses_midnight,
            'lunch_required' => $this->lunch_required,
            'lunch_start_time' => $this->lunch_start_time,
            'lunch_end_time' => $this->lunch_end_time,
            'lunch_duration_minutes' => $this->lunch_duration_minutes,
            'tolerance_minutes' => $this->tolerance_minutes,
            'overtime_enabled' => $this->overtime_enabled,
            'overtime_min_block_minutes' => $this->overtime_min_block_minutes,
            'max_daily_overtime_minutes' => $this->max_daily_overtime_minutes,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
