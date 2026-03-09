<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessAttendanceDayJob;
use App\Jobs\ProcessImportBatchJob;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\RawLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessImportBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_one_job_per_employee_date_group(): void
    {
        Queue::fake([ProcessAttendanceDayJob::class]);

        $batch = ImportBatch::factory()->create(['status' => 'processing']);
        $emp1 = Employee::factory()->create();
        $emp2 = Employee::factory()->create();

        // emp1: 2 logs on same day
        RawLog::factory()->create([
            'employee_id' => $emp1->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 08:00:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $emp1->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 17:00:00',
            'date_reference' => '2026-01-15',
        ]);

        // emp2: 1 log on same day
        RawLog::factory()->create([
            'employee_id' => $emp2->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 07:55:00',
            'date_reference' => '2026-01-15',
        ]);

        (new ProcessImportBatchJob($batch))->handle();

        Queue::assertPushed(ProcessAttendanceDayJob::class, 2);
    }

    public function test_dispatches_separate_jobs_for_different_dates(): void
    {
        Queue::fake([ProcessAttendanceDayJob::class]);

        $batch = ImportBatch::factory()->create(['status' => 'processing']);
        $employee = Employee::factory()->create();

        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 08:00:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-16 08:00:00',
            'date_reference' => '2026-01-16',
        ]);

        (new ProcessImportBatchJob($batch))->handle();

        Queue::assertPushed(ProcessAttendanceDayJob::class, 2);
    }

    public function test_sets_processing_state_with_total_rows(): void
    {
        Queue::fake([ProcessAttendanceDayJob::class]);

        $batch = ImportBatch::factory()->create(['status' => 'pending']);
        $employee = Employee::factory()->create();

        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 08:00:00',
            'date_reference' => '2026-01-15',
        ]);

        (new ProcessImportBatchJob($batch))->handle();

        $batch->refresh();
        $this->assertEquals('processing', $batch->status);
        $this->assertEquals(1, $batch->total_rows);
        $this->assertEquals(0, $batch->processed_rows);
    }

    public function test_dispatches_nothing_when_batch_has_no_raw_logs(): void
    {
        Queue::fake([ProcessAttendanceDayJob::class]);

        $batch = ImportBatch::factory()->create(['status' => 'processing']);

        (new ProcessImportBatchJob($batch))->handle();

        Queue::assertNotPushed(ProcessAttendanceDayJob::class);

        $batch->refresh();
        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(0, $batch->total_rows);
        $this->assertNotNull($batch->processed_at);
    }

    public function test_dispatches_correct_employee_and_date(): void
    {
        Queue::fake([ProcessAttendanceDayJob::class]);

        $batch = ImportBatch::factory()->create(['status' => 'processing']);
        $employee = Employee::factory()->create();

        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-03-20 09:00:00',
            'date_reference' => '2026-03-20',
        ]);

        (new ProcessImportBatchJob($batch))->handle();

        Queue::assertPushed(ProcessAttendanceDayJob::class, function ($job) use ($employee, $batch) {
            return $job->employeeId === $employee->id
                && $job->dateReference === '2026-03-20'
                && $job->importBatchId === $batch->id;
        });
    }

    public function test_ignores_raw_logs_from_other_batches(): void
    {
        Queue::fake([ProcessAttendanceDayJob::class]);

        $batch1 = ImportBatch::factory()->create(['status' => 'processing']);
        $batch2 = ImportBatch::factory()->create(['status' => 'processing']);
        $employee = Employee::factory()->create();

        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch1->id,
            'check_time' => '2026-01-15 08:00:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch2->id,
            'check_time' => '2026-01-16 08:00:00',
            'date_reference' => '2026-01-16',
        ]);

        (new ProcessImportBatchJob($batch1))->handle();

        Queue::assertPushed(ProcessAttendanceDayJob::class, 1);
        Queue::assertPushed(ProcessAttendanceDayJob::class, function ($job) use ($employee) {
            return $job->employeeId === $employee->id
                && $job->dateReference === '2026-01-15';
        });
    }
}
