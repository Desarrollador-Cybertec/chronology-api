<?php

namespace App\Domain\Attendance;

use Carbon\Carbon;

class LateCalculator
{
    /**
     * Calculate late arrival and early departure minutes.
     *
     * Late: employee arrived after shift start + tolerance.
     * Early departure: employee left before shift end.
     *
     * @param  AttendanceResult  $result  Partially-built result with first/last check
     * @param  Carbon  $dateReference  The date being processed
     */
    public function calculate(AttendanceResult $result, Carbon $dateReference): AttendanceResult
    {
        if (! $result->shift || $result->status !== 'present') {
            return $result;
        }

        $shift = $result->shift;

        $shiftStart = $dateReference->copy()->setTimeFromTimeString($shift->start_time);
        $shiftEnd = $dateReference->copy()->setTimeFromTimeString($shift->end_time);

        if ($shift->crosses_midnight) {
            $shiftEnd->addDay();
        }

        if ($result->firstCheck->gt($shiftStart->copy()->addMinutes($shift->tolerance_minutes))) {
            $result->lateMinutes = (int) $shiftStart->diffInMinutes($result->firstCheck);
        }

        if ($result->lastCheck->lt($shiftEnd)) {
            $result->earlyDepartureMinutes = (int) $result->lastCheck->diffInMinutes($shiftEnd);
        }

        return $result;
    }
}
