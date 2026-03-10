<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'date_reference' => $this->date_reference?->toDateString(),
            'first_check_in' => $this->first_check_in?->toDateTimeString(),
            'last_check_out' => $this->last_check_out?->toDateTimeString(),
            'worked_minutes' => $this->worked_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'overtime_diurnal_minutes' => $this->overtime_diurnal_minutes,
            'overtime_nocturnal_minutes' => $this->overtime_nocturnal_minutes,
            'late_minutes' => $this->late_minutes,
            'early_departure_minutes' => $this->early_departure_minutes,
            'status' => $this->status,
            'is_manually_edited' => $this->is_manually_edited,
            'edits' => AttendanceEditResource::collection($this->whenLoaded('edits')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
