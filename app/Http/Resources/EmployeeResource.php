<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internal_id' => $this->internal_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'department' => $this->department,
            'position' => $this->position,
            'is_active' => $this->is_active,
            'shift_assignments' => EmployeeShiftAssignmentResource::collection(
                $this->whenLoaded('shiftAssignments')
            ),
            'attendance_summary' => $this->when(
                $this->total_days_worked !== null,
                fn () => [
                    'total_days_worked' => (int) $this->total_days_worked,
                    'total_days_absent' => (int) $this->total_days_absent,
                    'total_days_incomplete' => (int) $this->total_days_incomplete,
                    'total_worked_minutes' => (int) $this->total_worked_minutes,
                    'total_overtime_minutes' => (int) $this->total_overtime_minutes,
                    'total_overtime_diurnal_minutes' => (int) $this->total_overtime_diurnal_minutes,
                    'total_overtime_nocturnal_minutes' => (int) $this->total_overtime_nocturnal_minutes,
                    'total_late_minutes' => (int) $this->total_late_minutes,
                    'total_early_departure_minutes' => (int) $this->total_early_departure_minutes,
                ],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
