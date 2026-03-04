<?php

namespace App\Domain\Attendance;

use Carbon\Carbon;

class OvertimeCalculator
{
    /**
     * Calculate overtime minutes with diurnal/nocturnal split.
     *
     * Overtime = lastCheck - shiftEnd (time actually worked past the shift).
     * Only counts complete blocks of overtime_min_block_minutes.
     * Caps at max_daily_overtime_minutes when configured.
     *
     * @param  AttendanceResult  $result  Partially-built result with first/last check
     * @param  Carbon  $dateReference  The date being processed
     * @param  string  $diurnalStartTime  Start of diurnal period (e.g. "06:00")
     * @param  string  $nocturnalStartTime  Start of nocturnal period (e.g. "20:00")
     */
    public function calculate(
        AttendanceResult $result,
        Carbon $dateReference,
        string $diurnalStartTime = '06:00',
        string $nocturnalStartTime = '20:00',
    ): AttendanceResult {
        if (! $result->shift || $result->status !== 'present' || ! $result->shift->overtime_enabled) {
            return $result;
        }

        $shift = $result->shift;

        $shiftEnd = $dateReference->copy()->setTimeFromTimeString($shift->end_time);

        if ($shift->crosses_midnight) {
            $shiftEnd->addDay();
        }

        if (! $result->lastCheck || $result->lastCheck->lte($shiftEnd)) {
            return $result;
        }

        $extraMinutes = (int) $shiftEnd->diffInMinutes($result->lastCheck);

        $blockMinutes = $shift->overtime_min_block_minutes ?: 60;
        $completedBlocks = (int) floor($extraMinutes / $blockMinutes);

        if ($completedBlocks < 1) {
            return $result;
        }

        $overtimeMinutes = $completedBlocks * $blockMinutes;

        if ($shift->max_daily_overtime_minutes > 0) {
            $overtimeMinutes = min($overtimeMinutes, $shift->max_daily_overtime_minutes);
        }

        $result->overtimeMinutes = $overtimeMinutes;

        $overtimeStart = $shiftEnd->copy();
        $overtimeEnd = $overtimeStart->copy()->addMinutes($overtimeMinutes);

        [$diurnal, $nocturnal] = $this->splitDiurnalNocturnal(
            $overtimeStart,
            $overtimeEnd,
            $diurnalStartTime,
            $nocturnalStartTime,
        );

        $result->overtimeDiurnalMinutes = $diurnal;
        $result->overtimeNocturnalMinutes = $nocturnal;

        return $result;
    }

    /**
     * Split a time range into diurnal and nocturnal minutes.
     *
     * @return array{int, int} [diurnal_minutes, nocturnal_minutes]
     */
    private function splitDiurnalNocturnal(
        Carbon $start,
        Carbon $end,
        string $diurnalStartTime,
        string $nocturnalStartTime,
    ): array {
        $diurnalStartHour = (int) explode(':', $diurnalStartTime)[0];
        $diurnalStartMin = (int) (explode(':', $diurnalStartTime)[1] ?? 0);
        $nocturnalStartHour = (int) explode(':', $nocturnalStartTime)[0];
        $nocturnalStartMin = (int) (explode(':', $nocturnalStartTime)[1] ?? 0);

        $diurnal = 0;
        $nocturnal = 0;
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $isDiurnal = $this->isInDiurnalPeriod($cursor, $diurnalStartHour, $diurnalStartMin, $nocturnalStartHour, $nocturnalStartMin);

            if ($isDiurnal) {
                $nextBoundary = $cursor->copy()->setHour($nocturnalStartHour)->setMinute($nocturnalStartMin)->setSecond(0);
                if ($nextBoundary->lte($cursor)) {
                    $nextBoundary->addDay();
                }
            } else {
                $nextBoundary = $cursor->copy()->setHour($diurnalStartHour)->setMinute($diurnalStartMin)->setSecond(0);
                if ($nextBoundary->lte($cursor)) {
                    $nextBoundary->addDay();
                }
            }

            $segmentEnd = $end->lt($nextBoundary) ? $end : $nextBoundary;
            $minutes = (int) $cursor->diffInMinutes($segmentEnd);

            if ($isDiurnal) {
                $diurnal += $minutes;
            } else {
                $nocturnal += $minutes;
            }

            $cursor = $segmentEnd->copy();
        }

        return [$diurnal, $nocturnal];
    }

    /**
     * Determine if a given time falls within the diurnal period.
     */
    private function isInDiurnalPeriod(
        Carbon $time,
        int $diurnalStartHour,
        int $diurnalStartMin,
        int $nocturnalStartHour,
        int $nocturnalStartMin,
    ): bool {
        $timeMinutes = $time->hour * 60 + $time->minute;
        $diurnalStart = $diurnalStartHour * 60 + $diurnalStartMin;
        $nocturnalStart = $nocturnalStartHour * 60 + $nocturnalStartMin;

        return $timeMinutes >= $diurnalStart && $timeMinutes < $nocturnalStart;
    }
}
