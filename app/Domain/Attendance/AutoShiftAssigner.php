<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;

class AutoShiftAssigner
{
    /**
     * Try to resolve a shift for an employee based on their first check-in time.
     *
     * Compares the check-in time against every active shift's start_time
     * using a configurable tolerance window. Returns the best matching Shift
     * without creating a persistent assignment.
     *
     * @param  Carbon  $dateReference  The date of the attendance record
     * @param  Carbon  $firstCheckIn  The employee's first punch of the day
     * @param  int  $toleranceMinutes  Window around shift start (±) to consider a match
     */
    public function resolve(
        Carbon $dateReference,
        Carbon $firstCheckIn,
        int $toleranceMinutes = 30,
    ): ?Shift {
        $shifts = Shift::query()->where('is_active', true)->get();

        if ($shifts->isEmpty()) {
            return null;
        }

        $bestShift = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($shifts as $shift) {
            $shiftStart = $dateReference->copy()->setTimeFromTimeString($shift->start_time);

            $windowStart = $shiftStart->copy()->subMinutes($toleranceMinutes);
            $windowEnd = $shiftStart->copy()->addMinutes($toleranceMinutes);

            if ($firstCheckIn->between($windowStart, $windowEnd)) {
                $distance = (int) abs($firstCheckIn->diffInMinutes($shiftStart));

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestShift = $shift;
                }
            }
        }

        return $bestShift;
    }
}
