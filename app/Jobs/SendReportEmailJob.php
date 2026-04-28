<?php

namespace App\Jobs;

use App\Mail\IndividualReportMail;
use App\Models\Report;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendReportEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Report $report) {}

    public function handle(): void
    {
        $this->report->load('employee');

        Mail::to($this->report->employee->email)
            ->send(new IndividualReportMail($this->report));
    }
}
