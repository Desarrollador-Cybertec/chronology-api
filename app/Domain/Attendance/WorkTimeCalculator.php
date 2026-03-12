<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkTimeCalculator
{
    /**
     * Build attendance day data from reduced logs and the resolved shift.
     *
     * Calculates first_check, last_check, worked_minutes, and status.
     * When a shift is assigned, worked time starts from the shift start
     * (not from the actual first check) if the employee arrives early.
     * Lunch deduction is applied externally via lunchMinutes parameter.
     *
     * @param  Collection  $logs  Reduced collection of RawLog models
     * @param  Shift|null  $shift  The employee's active shift for this date
     * @param  int  $lunchMinutes  Minutes to deduct for lunch (from LunchAnalyzer)
     * @param  Carbon|null  $dateReference  The date being processed
     * @return AttendanceResult Partially filled result
     */
    public function calculate(Collection $logs, ?Shift $shift = null, int $lunchMinutes = 0, ?Carbon $dateReference = null): AttendanceResult
    {
        if ($logs->isEmpty()) {
            return new AttendanceResult(status: 'absent', shift: $shift);
        }

        $sorted = $logs->sortBy('check_time')->values();
        $firstCheck = $sorted->first()->check_time;
        $lastCheck = $sorted->last()->check_time;

        if ($sorted->count() === 1) {
            return new AttendanceResult(
                firstCheck: $firstCheck,
                lastCheck: $firstCheck,
                status: 'incomplete',
                shift: $shift,
            );
        }

        $effectiveStart = $firstCheck;

        if ($shift && $dateReference) {
            $shiftStart = $dateReference->copy()->setTimeFromTimeString($shift->start_time);

            if ($firstCheck->lt($shiftStart)) {
                $effectiveStart = $shiftStart;
            }
        }

        $workedMinutes = (int) $effectiveStart->diffInMinutes($lastCheck);

        if ($lunchMinutes > 0) {
            $workedMinutes = max(0, $workedMinutes - $lunchMinutes);
        }

        return new AttendanceResult(
            firstCheck: $firstCheck,
            lastCheck: $lastCheck,
            workedMinutes: $workedMinutes,
            status: 'present',
            shift: $shift,
        );
    }
}
