<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    public function __construct(
        private LunchAnalyzer $lunchAnalyzer,
        private WorkTimeCalculator $workTimeCalculator,
        private LateCalculator $lateCalculator,
        private OvertimeCalculator $overtimeCalculator,
    ) {}

    /**
     * Calculate attendance metrics from pre-reduced logs.
     *
     * Pipeline: LunchAnalyzer → WorkTimeCalculator → LateCalculator → OvertimeCalculator
     *
     * @param  Collection  $reducedLogs  Noise-filtered RawLog entries
     * @param  Shift|null  $shift  Resolved shift for the employee
     * @param  Carbon  $dateReference  The date being processed
     * @param  string  $diurnalStartTime  Start of diurnal period
     * @param  string  $nocturnalStartTime  Start of nocturnal period
     */
    public function calculate(
        Collection $reducedLogs,
        ?Shift $shift,
        Carbon $dateReference,
        string $diurnalStartTime = '06:00',
        string $nocturnalStartTime = '20:00',
        int $lunchMarginMinutes = 15,
    ): AttendanceResult {

        $lunchMinutes = 0;
        if ($shift) {
            $lunchMinutes = $this->lunchAnalyzer->analyze($reducedLogs, $shift, $dateReference, $lunchMarginMinutes);
        }

        $result = $this->workTimeCalculator->calculate($reducedLogs, $shift, $lunchMinutes, $dateReference);
        $result = $this->lateCalculator->calculate($result, $dateReference);
        $result = $this->overtimeCalculator->calculate($result, $dateReference, $diurnalStartTime, $nocturnalStartTime);

        return $result;
    }
}
