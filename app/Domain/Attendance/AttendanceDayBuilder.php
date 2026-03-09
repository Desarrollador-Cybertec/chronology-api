<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Illuminate\Support\Collection;

class AttendanceDayBuilder
{
    /**
     * Build attendance day data from reduced logs and the resolved shift.
     *
     * @param  Collection  $logs  Reduced collection of RawLog models
     * @param  Shift|null  $shift  The employee's active shift for this date
     * @return AttendanceResult Partially filled result with first_check, last_check, worked_minutes, status
     */
    public function build(Collection $logs, ?Shift $shift = null): AttendanceResult
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

        $workedMinutes = (int) $firstCheck->diffInMinutes($lastCheck);

        if ($shift) {
            $workedMinutes = max(0, $workedMinutes - $shift->total_break_minutes);
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
