<?php

namespace App\Jobs;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Report;
use App\Services\LicenseService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report) {}

    public function handle(): void
    {
        $this->report->update(['status' => 'processing']);

        try {
            if ($this->report->type === 'individual') {
                $this->generateIndividual();
            } else {
                $this->generateGeneral();
            }

            $this->report->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            app(LicenseService::class)->reportUsage(
                'execution',
                1,
                'report_'.$this->report->id,
            );
        } catch (\Throwable $e) {
            $this->report->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function generateIndividual(): void
    {
        $employee = Employee::findOrFail($this->report->employee_id);

        $days = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date_reference', [
                $this->report->date_from->toDateString(),
                $this->report->date_to->toDateString(),
            ])
            ->orderBy('date_reference')
            ->get();

        $rows = $days->map(fn (AttendanceDay $day) => [
            'date' => $day->date_reference->toDateString(),
            'first_check_in' => $day->first_check_in?->format('H:i:s'),
            'last_check_out' => $day->last_check_out?->format('H:i:s'),
            'worked_minutes' => $day->worked_minutes,
            'late_minutes' => $day->late_minutes,
            'early_departure_minutes' => $day->early_departure_minutes,
            'overtime_minutes' => $day->overtime_minutes,
            'overtime_diurnal_minutes' => $day->overtime_diurnal_minutes,
            'overtime_nocturnal_minutes' => $day->overtime_nocturnal_minutes,
            'status' => $day->status,
        ])->toArray();

        $summary = [
            'employee_name' => $employee->full_name,
            'employee_internal_id' => $employee->internal_id,
            'total_days' => $days->count(),
            'days_present' => $days->where('status', 'present')->count(),
            'days_absent' => $days->where('status', 'absent')->count(),
            'days_incomplete' => $days->where('status', 'incomplete')->count(),
            'times_late' => $days->where('late_minutes', '>', 0)->count(),
            'total_late_minutes' => $days->sum('late_minutes'),
            'total_worked_minutes' => $days->sum('worked_minutes'),
            'total_overtime_minutes' => $days->sum('overtime_minutes'),
            'total_overtime_diurnal_minutes' => $days->sum('overtime_diurnal_minutes'),
            'total_overtime_nocturnal_minutes' => $days->sum('overtime_nocturnal_minutes'),
            'total_early_departure_minutes' => $days->sum('early_departure_minutes'),
        ];

        $this->report->update([
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    private function generateGeneral(): void
    {
        $days = AttendanceDay::query()
            ->with('employee')
            ->whereBetween('date_reference', [
                $this->report->date_from->toDateString(),
                $this->report->date_to->toDateString(),
            ])
            ->orderBy('date_reference')
            ->orderBy('employee_id')
            ->get();

        $rows = $days->map(fn (AttendanceDay $day) => [
            'employee_code' => $day->employee->internal_id,
            'employee_name' => $day->employee->full_name,
            'date' => $day->date_reference->toDateString(),
            'first_check_in' => $day->first_check_in?->format('H:i:s'),
            'last_check_out' => $day->last_check_out?->format('H:i:s'),
            'worked_minutes' => $day->worked_minutes,
            'late_minutes' => $day->late_minutes,
            'early_departure_minutes' => $day->early_departure_minutes,
            'overtime_minutes' => $day->overtime_minutes,
            'overtime_diurnal_minutes' => $day->overtime_diurnal_minutes,
            'overtime_nocturnal_minutes' => $day->overtime_nocturnal_minutes,
            'status' => $day->status,
        ])->toArray();

        $employeeIds = $days->pluck('employee_id')->unique();

        $summary = [
            'total_employees' => $employeeIds->count(),
            'total_days' => $days->count(),
            'days_present' => $days->where('status', 'present')->count(),
            'days_absent' => $days->where('status', 'absent')->count(),
            'days_incomplete' => $days->where('status', 'incomplete')->count(),
            'total_late_entries' => $days->where('late_minutes', '>', 0)->count(),
            'total_late_minutes' => $days->sum('late_minutes'),
            'total_worked_minutes' => $days->sum('worked_minutes'),
            'total_overtime_minutes' => $days->sum('overtime_minutes'),
            'total_overtime_diurnal_minutes' => $days->sum('overtime_diurnal_minutes'),
            'total_overtime_nocturnal_minutes' => $days->sum('overtime_nocturnal_minutes'),
            'total_early_departure_minutes' => $days->sum('early_departure_minutes'),
        ];

        $this->report->update([
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }
}
