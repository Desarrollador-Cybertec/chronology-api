<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Models\RawLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ImportBatch $batch) {}

    /**
     * Group raw_logs by employee_id + date_reference and dispatch
     * a ProcessAttendanceDayJob for each group.
     */
    public function handle(): void
    {
        $groups = RawLog::query()
            ->where('import_batch_id', $this->batch->id)
            ->select('employee_id', 'date_reference')
            ->distinct()
            ->get();

        foreach ($groups as $group) {
            ProcessAttendanceDayJob::dispatch(
                $group->employee_id,
                $group->date_reference->toDateString(),
            );
        }

        $this->batch->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
