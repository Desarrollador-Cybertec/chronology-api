<?php

namespace App\Jobs;

use App\Domain\Attendance\AttendanceEngine;
use App\Models\AttendanceDay;
use App\Models\RawLog;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAttendanceDayJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $employeeId,
        public string $dateReference,
    ) {}

    /**
     * Fetch raw_logs for this employee+date, run AttendanceEngine,
     * and upsert the resulting AttendanceDay.
     */
    public function handle(AttendanceEngine $engine): void
    {
        $date = Carbon::parse($this->dateReference);

        $rawLogs = RawLog::query()
            ->where('employee_id', $this->employeeId)
            ->whereDate('date_reference', $date->toDateString())
            ->orderBy('check_time')
            ->get();

        $noiseWindow = (int) SystemSetting::getValue('noise_window_minutes', '60');
        $autoAssign = SystemSetting::getValue('auto_assign_shift', 'true') === 'true';
        $autoAssignTolerance = (int) SystemSetting::getValue('auto_assign_tolerance_minutes', '30');

        $result = $engine->process(
            $rawLogs,
            $this->employeeId,
            $date,
            $noiseWindow,
            $autoAssign,
            $autoAssignTolerance,
        );

        AttendanceDay::updateOrCreate(
            [
                'employee_id' => $this->employeeId,
                'date_reference' => $date,
            ],
            [
                'shift_id' => $result->shift?->id,
                'first_check_in' => $result->firstCheck,
                'last_check_out' => $result->lastCheck,
                'worked_minutes' => $result->workedMinutes,
                'overtime_minutes' => $result->overtimeMinutes,
                'overtime_diurnal_minutes' => $result->overtimeDiurnalMinutes,
                'overtime_nocturnal_minutes' => $result->overtimeNocturnalMinutes,
                'late_minutes' => $result->lateMinutes,
                'early_departure_minutes' => $result->earlyDepartureMinutes,
                'status' => $result->status,
            ],
        );
    }
}
