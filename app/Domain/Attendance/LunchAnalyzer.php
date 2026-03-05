<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LunchAnalyzer
{
    /**
     * Analyze lunch break from reduced logs and calculate effective lunch deduction.
     *
     * When the shift has lunch_start_time and lunch_end_time defined,
     * the analyzer looks for intermediate punches that fall within or near
     * the lunch window to determine the actual lunch break taken.
     *
     * @param  Collection  $reducedLogs  Noise-filtered RawLog entries (sorted by check_time)
     * @param  Shift  $shift  The resolved shift with lunch configuration
     * @param  Carbon  $dateReference  The date being processed
     * @return int Minutes to deduct for lunch
     */
    public function analyze(Collection $reducedLogs, Shift $shift, Carbon $dateReference, int $lunchMarginMinutes = 15): int
    {
        if (! $shift->lunch_required) {
            return 0;
        }

        if ($reducedLogs->count() < 2) {
            return 0;
        }

        if ($shift->lunch_start_time && $shift->lunch_end_time) {
            return $this->analyzeWithLunchWindow($reducedLogs, $shift, $dateReference, $lunchMarginMinutes);
        }

        return $shift->lunch_duration_minutes ?? 0;
    }

    /**
     * Analyze lunch using defined lunch window times and intermediate punches.
     *
     * Looks for a pair of punches where the employee left during the lunch
     * window (exit punch) and returned after (entry punch). If found, uses
     * the actual break duration. Otherwise falls back to the configured
     * lunch_duration_minutes.
     */
    private function analyzeWithLunchWindow(Collection $reducedLogs, Shift $shift, Carbon $dateReference, int $lunchMarginMinutes = 15): int
    {
        $lunchStart = $dateReference->copy()->setTimeFromTimeString($shift->lunch_start_time);
        $lunchEnd = $dateReference->copy()->setTimeFromTimeString($shift->lunch_end_time);

        $sorted = $reducedLogs->sortBy('check_time')->values();

        if ($sorted->count() < 2) {
            return $shift->lunch_duration_minutes ?? 0;
        }

        $firstCheck = $sorted->first()->check_time;
        $lastCheck = $sorted->last()->check_time;

        if ($firstCheck->gte($lunchEnd) || $lastCheck->lte($lunchStart)) {
            return 0;
        }

        if ($sorted->count() >= 4) {
            $lunchExit = null;
            $lunchReturn = null;

            for ($i = 1; $i < $sorted->count() - 1; $i++) {
                $punchTime = $sorted[$i]->check_time;

                if (! $lunchExit && $this->isNearLunchStart($punchTime, $lunchStart, $lunchMarginMinutes)) {
                    $lunchExit = $punchTime;

                    continue;
                }

                if ($lunchExit && ! $lunchReturn && $this->isNearLunchEnd($punchTime, $lunchStart, $lunchEnd, $lunchMarginMinutes)) {
                    $lunchReturn = $punchTime;

                    break;
                }
            }

            if ($lunchExit && $lunchReturn) {
                return (int) $lunchExit->diffInMinutes($lunchReturn);
            }
        }

        return $shift->lunch_duration_minutes ?? 0;
    }

    /**
     * Check if a punch time is near the lunch start (within margin).
     */
    private function isNearLunchStart(Carbon $punchTime, Carbon $lunchStart, int $marginMinutes = 15): bool
    {
        $windowStart = $lunchStart->copy()->subMinutes($marginMinutes);
        $windowEnd = $lunchStart->copy()->addMinutes($marginMinutes);

        return $punchTime->between($windowStart, $windowEnd);
    }

    /**
     * Check if a punch time is near the lunch end (between lunch_start + margin and lunch_end + 2*margin).
     */
    private function isNearLunchEnd(Carbon $punchTime, Carbon $lunchStart, Carbon $lunchEnd, int $marginMinutes = 15): bool
    {
        $windowStart = $lunchStart->copy()->addMinutes($marginMinutes);
        $windowEnd = $lunchEnd->copy()->addMinutes($marginMinutes * 2);

        return $punchTime->between($windowStart, $windowEnd);
    }
}
