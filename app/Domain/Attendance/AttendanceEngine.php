<?php

namespace App\Domain\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceEngine
{
    public function __construct(
        private LogReducer $logReducer,
        private ScheduleResolver $scheduleResolver,
        private ShiftResolver $shiftResolver,
        private AttendanceCalculator $calculator,
    ) {}

    /**
     * Process attendance for an employee on a given date.
     *
     * Pipeline: ScheduleResolver → LogReducer → ShiftResolver → AttendanceCalculator
     *
     * @param  Collection  $rawLogs  RawLog entries for this employee+date
     * @param  int  $employeeId  The employee being processed
     * @param  Carbon  $dateReference  The date being processed
     * @param  int  $noiseWindowMinutes  Noise window for LogReducer
     * @param  string  $diurnalStartTime  Start of diurnal period
     * @param  string  $nocturnalStartTime  Start of nocturnal period
     * @param  int  $lunchMarginMinutes  Margin for detecting lunch punches
     */
    public function process(
        Collection $rawLogs,
        int $employeeId,
        Carbon $dateReference,
        int $noiseWindowMinutes = 60,
        string $diurnalStartTime = '06:00',
        string $nocturnalStartTime = '20:00',
        int $lunchMarginMinutes = 15,
    ): AttendanceResult {
        $schedule = $this->scheduleResolver->resolve($employeeId, $dateReference);

        if (! $schedule->isWorkingDay) {
            return AttendanceResult::rest();
        }

        $reducedLogs = $this->logReducer->reduce($rawLogs, $noiseWindowMinutes);

        $shift = $schedule->shift ?? $this->shiftResolver->resolve($employeeId, $dateReference);

        return $this->calculator->calculate($reducedLogs, $shift, $dateReference, $diurnalStartTime, $nocturnalStartTime, $lunchMarginMinutes);
    }
}
