<?php

namespace App\Jobs;

use App\Mail\IndividualReportMail;
use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

class SendReportEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;
    public int $backoff = 60; // reintentar cada 60s si el reporte aún no está listo

    public function __construct(public Report $report) {}

    public function handle(): void
    {
        // Recargar para obtener el estado más reciente
        $this->report->refresh()->load('employee');

        if ($this->report->status !== 'completed') {
            $this->release(60);
            return;
        }

        if (! $this->report->employee?->email) {
            return;
        }

        try {
            Mail::to($this->report->employee->email)
                ->send(new IndividualReportMail($this->report));
        } catch (UnexpectedResponseException $e) {
            // Errores SMTP permanentes (550 usuario inexistente, etc.) no tienen caso reintentar
            if (str_starts_with((string) $e->getCode(), '5')) {
                $this->fail($e);
                return;
            }
            throw $e;
        }
    }
}
