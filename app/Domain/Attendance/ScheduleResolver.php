<?php

namespace App\Domain\Attendance;

use App\Models\EmployeeScheduleException;
use App\Models\EmployeeShiftAssignment;
use Carbon\Carbon;

class ScheduleResolver
{
    /**
     * Resolve schedule info for an employee on a given date.
     *
     * Priority:
     * 1. EmployeeScheduleException (per-date override)
     * 2. EmployeeShiftAssignment.work_days (weekly pattern)
     */
    public function resolve(int $employeeId, Carbon $date): ScheduleResult
    {
        $exception = EmployeeScheduleException::query()
            ->with('shift.breaks')
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($exception) {
            return new ScheduleResult(
                isWorkingDay: $exception->is_working_day,
                shift: $exception->is_working_day ? $exception->shift : null,
                source: 'exception',
            );
        }

        $dayOfWeek = $date->dayOfWeek;

        $assignments = EmployeeShiftAssignment::query()
            ->with('shift.breaks')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_date')
            ->get();

        if ($assignments->isEmpty()) {
            return new ScheduleResult(
                isWorkingDay: true,
                shift: null,
                source: 'none',
            );
        }

        $matching = $assignments->first(fn ($a) => in_array($dayOfWeek, $a->work_days ?? [1, 2, 3, 4, 5]));

        if (! $matching) {
            return new ScheduleResult(
                isWorkingDay: false,
                shift: null,
                source: 'assignment',
            );
        }

        return new ScheduleResult(
            isWorkingDay: true,
            shift: $matching->shift,
            source: 'assignment',
        );
    }
}
