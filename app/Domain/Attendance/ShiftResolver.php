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
     * Supports multiple concurrent assignments with different work_days.
     * Returns the shift from the assignment whose work_days includes the
     * day of week of the given date, preferring the most recently started one.
     */
    public function resolve(int $employeeId, Carbon $date): ?Shift
    {
        $dayOfWeek = $date->dayOfWeek;

        $assignment = EmployeeShiftAssignment::query()
            ->with('shift.breaks')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_date')
            ->get()
            ->first(fn ($a) => in_array($dayOfWeek, $a->work_days ?? [1, 2, 3, 4, 5]));

        return $assignment?->shift;
    }
}
