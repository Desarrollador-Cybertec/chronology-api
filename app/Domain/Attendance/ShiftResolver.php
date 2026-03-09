<?php

namespace App\Domain\Attendance;

use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use Carbon\Carbon;

class ShiftResolver
{
    /**
     * Resolve the active shift for an employee on a given date.
     *
     * Looks up the most recent EmployeeShiftAssignment that covers the date.
     */
    public function resolve(int $employeeId, Carbon $date): ?Shift
    {
        $assignment = EmployeeShiftAssignment::query()
            ->with('shift.breaks')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_date')
            ->first();

        return $assignment?->shift;
    }
}
