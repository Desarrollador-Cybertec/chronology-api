<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LunchAnalyzer
{
    /**
     * Analyze breaks from reduced logs and calculate total break deduction.
     *
     * If the shift has configured break blocks (shift_breaks table), their
     * total duration is used. Otherwise, falls back to legacy single-lunch
     * analysis using lunch_start_time / lunch_end_time columns.
     *
     * @param  Collection  $reducedLogs  Noise-filtered RawLog entries (sorted by check_time)
     * @param  Shift  $shift  The resolved shift with break/lunch configuration
     * @param  Carbon  $dateReference  The date being processed
     * @return int Minutes to deduct for breaks
     */
    public function analyze(Collection $reducedLogs, Shift $shift, Carbon $dateReference, int $lunchMarginMinutes = 15): int
    {
        if ($reducedLogs->count() < 2) {
            return 0;
        }

        if ($shift->relationLoaded('breaks') && $shift->breaks->isNotEmpty()) {
            return $this->analyzeWithBreakBlocks($reducedLogs, $shift, $dateReference, $lunchMarginMinutes);
        }

        if (! $shift->lunch_required) {
            return 0;
        }

        if ($shift->lunch_start_time && $shift->lunch_end_time) {
            return $this->analyzeWithLunchWindow($reducedLogs, $shift, $dateReference, $lunchMarginMinutes);
        }

        return $shift->lunch_duration_minutes ?? 0;
    }

    /**
     * Analyze using configured break blocks from the shift_breaks table.
     *
     * For each break block, attempts to detect actual punch pairs within
     * the break window. If detected, uses the actual duration; otherwise
     * uses the configured duration_minutes for that block.
     */
    private function analyzeWithBreakBlocks(Collection $reducedLogs, Shift $shift, Carbon $dateReference, int $marginMinutes): int
    {
        $sorted = $reducedLogs->sortBy('check_time')->values();
        $firstCheck = $sorted->first()->check_time;
        $lastCheck = $sorted->last()->check_time;
        $totalDeduction = 0;

        foreach ($shift->breaks as $breakBlock) {
            $breakStart = $dateReference->copy()->setTimeFromTimeString($breakBlock->start_time);
            $breakEnd = $dateReference->copy()->setTimeFromTimeString($breakBlock->end_time);

            if ($firstCheck->gte($breakEnd) || $lastCheck->lte($breakStart)) {
                continue;
            }

            if ($sorted->count() >= 4) {
                $detected = $this->detectBreakPunch($sorted, $breakStart, $breakEnd, $marginMinutes);

                if ($detected !== null) {
                    $totalDeduction += $detected;

                    continue;
                }
            }

            $totalDeduction += $breakBlock->duration_minutes;
        }

        return $totalDeduction;
    }

    /**
     * Try to detect a punch pair (exit + return) within a break window.
     *
     * @return int|null Actual break minutes if detected, null otherwise
     */
    private function detectBreakPunch(Collection $sorted, Carbon $breakStart, Carbon $breakEnd, int $marginMinutes): ?int
    {
        $exitPunch = null;
        $returnPunch = null;

        for ($i = 1; $i < $sorted->count() - 1; $i++) {
            $punchTime = $sorted[$i]->check_time;

            if (! $exitPunch && $this->isNearTime($punchTime, $breakStart, $marginMinutes)) {
                $exitPunch = $punchTime;

                continue;
            }

            if ($exitPunch && ! $returnPunch && $this->isNearTime($punchTime, $breakEnd, $marginMinutes)) {
                $returnPunch = $punchTime;

                break;
            }
        }

        if ($exitPunch && $returnPunch) {
            return (int) $exitPunch->diffInMinutes($returnPunch);
        }

        return null;
    }

    /**
     * Check if a punch time is near a target time (within margin).
     */
    private function isNearTime(Carbon $punchTime, Carbon $target, int $marginMinutes): bool
    {
        $windowStart = $target->copy()->subMinutes($marginMinutes);
        $windowEnd = $target->copy()->addMinutes($marginMinutes);

        return $punchTime->between($windowStart, $windowEnd);
    }

    /**
     * Legacy: Analyze lunch using defined lunch window times and intermediate punches.
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
