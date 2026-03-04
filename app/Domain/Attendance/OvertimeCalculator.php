<?php

namespace App\Domain\Attendance;

use Carbon\Carbon;

class OvertimeCalculator
{
    private const DIURNAL_START_HOUR = 6;

    private const DIURNAL_END_HOUR = 18;

    /**
     * Calculate overtime minutes with diurnal/nocturnal split.
     *
     * Only counts overtime if the extra time >= overtime_min_block_minutes.
     * Caps at max_daily_overtime_minutes when configured.
     *
     * @param  AttendanceResult  $result  Partially-built result with worked_minutes
     * @param  Carbon  $dateReference  The date being processed
     */
    public function calculate(AttendanceResult $result, Carbon $dateReference): AttendanceResult
    {
        if (! $result->shift || $result->status !== 'present' || ! $result->shift->overtime_enabled) {
            return $result;
        }

        $shift = $result->shift;

        $shiftStart = $dateReference->copy()->setTimeFromTimeString($shift->start_time);
        $shiftEnd = $dateReference->copy()->setTimeFromTimeString($shift->end_time);

        if ($shift->crosses_midnight) {
            $shiftEnd->addDay();
        }

        $scheduledMinutes = (int) $shiftStart->diffInMinutes($shiftEnd);

        if ($shift->lunch_required) {
            $scheduledMinutes -= $shift->lunch_duration_minutes;
        }

        $extraMinutes = $result->workedMinutes - $scheduledMinutes;

        if ($extraMinutes < $shift->overtime_min_block_minutes) {
            return $result;
        }

        if ($shift->max_daily_overtime_minutes > 0) {
            $extraMinutes = min($extraMinutes, $shift->max_daily_overtime_minutes);
        }

        $result->overtimeMinutes = $extraMinutes;

        $overtimeStart = $shiftEnd->copy();
        $overtimeEnd = $overtimeStart->copy()->addMinutes($extraMinutes);

        [$diurnal, $nocturnal] = $this->splitDiurnalNocturnal($overtimeStart, $overtimeEnd);

        $result->overtimeDiurnalMinutes = $diurnal;
        $result->overtimeNocturnalMinutes = $nocturnal;

        return $result;
    }

    /**
     * Split a time range into diurnal (6:00–18:00) and nocturnal (18:00–6:00) minutes.
     *
     * @return array{int, int} [diurnal_minutes, nocturnal_minutes]
     */
    private function splitDiurnalNocturnal(Carbon $start, Carbon $end): array
    {
        $diurnal = 0;
        $nocturnal = 0;
        $cursor = $start->copy();

        while ($cursor->lt($end)) {
            $hour = $cursor->hour;
            $isDiurnal = $hour >= self::DIURNAL_START_HOUR && $hour < self::DIURNAL_END_HOUR;

            if ($isDiurnal) {
                $nextBoundary = $cursor->copy()->setHour(self::DIURNAL_END_HOUR)->setMinute(0)->setSecond(0);
            } elseif ($hour >= self::DIURNAL_END_HOUR) {
                $nextBoundary = $cursor->copy()->addDay()->setHour(self::DIURNAL_START_HOUR)->setMinute(0)->setSecond(0);
            } else {
                $nextBoundary = $cursor->copy()->setHour(self::DIURNAL_START_HOUR)->setMinute(0)->setSecond(0);
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
}
