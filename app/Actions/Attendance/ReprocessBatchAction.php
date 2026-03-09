<?php

namespace App\Actions\Attendance;

use App\Jobs\ProcessImportBatchJob;
use App\Models\AttendanceDay;
use App\Models\ImportBatch;
use App\Models\RawLog;

class ReprocessBatchAction
{
    /**
     * Delete attendance_days generated from this batch's raw_logs
     * and re-dispatch the processing jobs.
     *
     * @return array{deleted: int, groups: int}
     */
    public function execute(ImportBatch $batch): array
    {
        $affectedPairs = RawLog::query()
            ->where('import_batch_id', $batch->id)
            ->select('employee_id', 'date_reference')
            ->distinct()
            ->get();

        $deleted = 0;

        foreach ($affectedPairs as $pair) {
            $deleted += AttendanceDay::query()
                ->where('employee_id', $pair->employee_id)
                ->whereDate('date_reference', $pair->date_reference->toDateString())
                ->where('is_manually_edited', false)
                ->delete();
        }

        $batch->update([
            'status' => 'processing',
            'processed_at' => null,
            'total_rows' => 0,
            'processed_rows' => 0,
        ]);

        ProcessImportBatchJob::dispatch($batch);

        return [
            'deleted' => $deleted,
            'groups' => $affectedPairs->count(),
        ];
    }
}
