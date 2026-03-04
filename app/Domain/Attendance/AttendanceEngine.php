<?php

namespace App\Domain\Attendance;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceEngine
{
    public function __construct(
        private LogReducer $logReducer,
        private ShiftResolver $shiftResolver,
        private AttendanceCalculator $calculator,
    ) {}

    /**
     * Process attendance for an employee on a given date.
     *
     * Pipeline: LogReducer → ShiftResolver → AttendanceCalculator
     * (which internally runs: AttendanceDayBuilder → LateCalculator → OvertimeCalculator)
     *
     * @param  Collection  $rawLogs  RawLog entries for this employee+date
     * @param  int  $employeeId  The employee being processed
     * @param  Carbon  $dateReference  The date being processed
     * @param  int  $noiseWindowMinutes  Noise window for LogReducer
     */
    public function process(
        Collection $rawLogs,
        int $employeeId,
        Carbon $dateReference,
        int $noiseWindowMinutes = 60,
    ): AttendanceResult {
        $reducedLogs = $this->logReducer->reduce($rawLogs, $noiseWindowMinutes);

        $shift = $this->shiftResolver->resolve($employeeId, $dateReference);

        return $this->calculator->calculate($reducedLogs, $shift, $dateReference);
    }
}
