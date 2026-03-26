<?php

namespace App\Jobs;

use App\Domain\Attendance\AttendanceEngine;
use App\Models\AttendanceDay;
use App\Models\ImportBatch;
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
        public ?int $importBatchId = null,
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
            ->get()
            ->unique('check_time')
            ->values();

        $noiseWindow = (int) SystemSetting::getValue('noise_window_minutes', '60');
        $diurnalStart = SystemSetting::getValue('diurnal_start_time', '06:00');
        $nocturnalStart = SystemSetting::getValue('nocturnal_start_time', '20:00');
        $lunchMargin = (int) SystemSetting::getValue('lunch_margin_minutes', '15');

        $result = $engine->process(
            $rawLogs,
            $this->employeeId,
            $date,
            $noiseWindow,
            $diurnalStart,
            $nocturnalStart,
            $lunchMargin,
        );

        AttendanceDay::updateOrCreate(
            [
                'employee_id' => $this->employeeId,
                'date_reference' => $date,
            ],
            [
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

        if ($this->importBatchId) {
            $batch = ImportBatch::find($this->importBatchId);

            if ($batch) {
                $batch->increment('processed_rows');
                $batch->refresh();

                if ($batch->processed_rows >= $batch->total_rows) {
                    $batch->update([
                        'status' => 'completed',
                        'processed_at' => now(),
                    ]);
                }
            }
        }
    }
}
