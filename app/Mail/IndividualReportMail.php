<?php

namespace App\Mail;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class IndividualReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Report $report) {}

    public function envelope(): Envelope
    {
        $employee = $this->report->employee;
        $from     = $this->report->date_from->format('d/m/Y');
        $to       = $this->report->date_to->format('d/m/Y');

        return new Envelope(
            subject: "Reporte de Asistencia — {$employee->full_name} ({$from} al {$to})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.individual_report',
            with: [
                'employeeName' => $this->report->employee->full_name,
                'dateFrom'     => $this->report->date_from->format('d/m/Y'),
                'dateTo'       => $this->report->date_to->format('d/m/Y'),
                'summary'      => $this->report->summary,
            ],
        );
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('reports.individual', [
            'report'  => $this->report,
            'summary' => $this->report->summary,
            'rows'    => $this->report->rows,
        ]);

        $filename = 'reporte_' . str_replace(' ', '_', strtolower($this->report->employee->full_name))
            . '_' . $this->report->date_from->format('Y-m-d') . '.pdf';

        return [
            Attachment::fromData(fn () => $pdf->output(), $filename)
                ->withMime('application/pdf'),
        ];
    }
}
