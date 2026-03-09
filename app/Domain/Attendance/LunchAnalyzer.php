<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LunchAnalyzer
{
    /**
     * Analyze break blocks from reduced logs and calculate total break deduction.
     *
     * For each configured break block, attempts to detect actual punch pairs
     * within the break window. If detected, uses the actual duration;
     * otherwise uses the configured duration_minutes for that block.
     *
     * @param  Collection  $reducedLogs  Noise-filtered RawLog entries (sorted by check_time)
     * @param  Shift  $shift  The resolved shift with breaks loaded
     * @param  Carbon  $dateReference  The date being processed
     * @return int Minutes to deduct for breaks
     */
    public function analyze(Collection $reducedLogs, Shift $shift, Carbon $dateReference, int $marginMinutes = 15): int
    {
        if ($reducedLogs->count() < 2) {
            return 0;
        }

        if ($shift->breaks->isEmpty()) {
            return 0;
        }

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
}
