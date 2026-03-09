<?php

namespace App\Domain\Attendance;

use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use Carbon\Carbon;

class AutoShiftAssigner
{
    /**
     * Try to resolve a shift for an employee based on their first check-in time.
     *
     * Compares the check-in time against every active shift's start_time
     * using each shift's own tolerance_minutes window. When a match is found, creates
     * a persistent EmployeeShiftAssignment so subsequent days use the same shift.
     *
     * @param  int  $employeeId  The employee being processed
     * @param  Carbon  $dateReference  The date of the attendance record
     * @param  Carbon  $firstCheckIn  The employee's first punch of the day
     */
    public function resolve(
        int $employeeId,
        Carbon $dateReference,
        Carbon $firstCheckIn,
    ): ?Shift {
        // Skip if the employee already has an active shift assignment covering this date
        $existingAssignment = EmployeeShiftAssignment::query()
            ->with('shift.breaks')
            ->where('employee_id', $employeeId)
            ->whereDate('effective_date', '<=', $dateReference->toDateString())
            ->where(function ($q) use ($dateReference) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $dateReference->toDateString());
            })
            ->first();

        if ($existingAssignment) {
            return $existingAssignment->shift;
        }

        $shifts = Shift::query()->with('breaks')->where('is_active', true)->get();

        if ($shifts->isEmpty()) {
            return null;
        }

        $bestShift = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($shifts as $shift) {
            $shiftStart = $dateReference->copy()->setTimeFromTimeString($shift->start_time);
            $tolerance = $shift->tolerance_minutes ?? 10;

            $windowStart = $shiftStart->copy()->subMinutes($tolerance);
            $windowEnd = $shiftStart->copy()->addMinutes($tolerance);

            if ($firstCheckIn->between($windowStart, $windowEnd)) {
                $distance = (int) abs($firstCheckIn->diffInMinutes($shiftStart));

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestShift = $shift;
                }
            }
        }

        if ($bestShift) {
            EmployeeShiftAssignment::create([
                'employee_id' => $employeeId,
                'shift_id' => $bestShift->id,
                'effective_date' => $dateReference->toDateString(),
                'work_days' => [1, 2, 3, 4, 5],
            ]);
        }

        return $bestShift;
    }
}
