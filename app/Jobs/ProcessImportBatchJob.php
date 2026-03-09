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

        $total = $groups->count();

        if ($total === 0) {
            $this->batch->update([
                'status' => 'completed',
                'total_rows' => 0,
                'processed_rows' => 0,
                'processed_at' => now(),
            ]);

            return;
        }

        $this->batch->update([
            'status' => 'processing',
            'total_rows' => $total,
            'processed_rows' => 0,
        ]);

        foreach ($groups as $group) {
            ProcessAttendanceDayJob::dispatch(
                $group->employee_id,
                $group->date_reference->toDateString(),
                $this->batch->id,
            );
        }
    }
}
