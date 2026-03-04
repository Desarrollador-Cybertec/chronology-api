<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessImportBatchJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ImportBatch $batch) {}

    /**
     * Process the import batch through the attendance engine.
     * This will be fully implemented in Sprint 4.
     */
    public function handle(): void
    {
        $this->batch->update([
            'status' => 'completed',
            'processed_at' => now(),
        ]);
    }
}
