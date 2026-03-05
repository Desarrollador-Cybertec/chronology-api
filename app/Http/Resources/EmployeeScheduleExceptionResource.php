<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeScheduleExceptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->toDateString(),
            'shift_id' => $this->shift_id,
            'is_working_day' => $this->is_working_day,
            'reason' => $this->reason,
            'shift' => new ShiftResource($this->whenLoaded('shift')),
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
