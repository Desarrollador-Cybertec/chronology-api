<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchBatchReportEmailsJob implements ShouldQueue
{
    use Queueable;

    // 15 emails por hora = 1 cada 240 segundos
    private const SECONDS_PER_EMAIL = 240;

    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public int $generatedBy,
    ) {}

    public function handle(): void
    {
        // Empleados activos con email registrado
        $employees = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $slot = 0;

        foreach ($employees as $employee) {
            // Buscar reporte individual completado para este empleado y período
            $report = Report::query()
                ->where('type', 'individual')
                ->where('employee_id', $employee->id)
                ->where('date_from', $this->dateFrom)
                ->where('date_to', $this->dateTo)
                ->where('status', 'completed')
                ->latest()
                ->first();

            // Si no existe, crearlo y encolarlo primero
            if (! $report) {
                $report = Report::create([
                    'type'         => 'individual',
                    'employee_id'  => $employee->id,
                    'date_from'    => $this->dateFrom,
                    'date_to'      => $this->dateTo,
                    'generated_by' => $this->generatedBy,
                    'status'       => 'pending',
                ]);

                // El reporte se genera primero, luego se envía el email
                GenerateReportJob::dispatch($report);
            }

            $delaySeconds = $slot * self::SECONDS_PER_EMAIL;

            SendReportEmailJob::dispatch($report)
                ->delay(now()->addSeconds($delaySeconds));

            $slot++;
        }
    }
}
