<?php

namespace App\Domain\Attendance;

use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    public function __construct(
        private AttendanceDayBuilder $dayBuilder,
        private LateCalculator $lateCalculator,
        private OvertimeCalculator $overtimeCalculator,
    ) {}

    /**
     * Calculate attendance metrics from pre-reduced logs.
     *
     * Coordinates AttendanceDayBuilder, LateCalculator, and OvertimeCalculator.
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
    ): AttendanceResult {
        $result = $this->dayBuilder->build($reducedLogs, $shift);
        $result = $this->lateCalculator->calculate($result, $dateReference);
        $result = $this->overtimeCalculator->calculate($result, $dateReference, $diurnalStartTime, $nocturnalStartTime);

        return $result;
    }
}
