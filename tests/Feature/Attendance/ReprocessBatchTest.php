<?php

namespace Tests\Feature\Attendance;

use App\Jobs\ProcessImportBatchJob;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReprocessBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private ImportBatch $batch;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->superadmin = User::factory()->superadmin()->create();
        $this->employee = Employee::factory()->create();

        $this->batch = ImportBatch::factory()->completed()->create([
            'uploaded_by' => $this->superadmin->id,
        ]);

        RawLog::factory()->create([
            'employee_id' => $this->employee->id,
            'import_batch_id' => $this->batch->id,
            'check_time' => '2026-01-15 08:00:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $this->employee->id,
            'import_batch_id' => $this->batch->id,
            'check_time' => '2026-01-15 17:00:00',
            'date_reference' => '2026-01-15',
        ]);
    }

    public function test_superadmin_can_reprocess_batch(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'deleted_attendance_days',
                'groups_to_process',
            ]);
    }

    public function test_reprocess_deletes_attendance_days(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'is_manually_edited' => false,
        ]);

        $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_reprocess_preserves_manually_edited_days(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'is_manually_edited' => true,
        ]);

        $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $this->assertDatabaseCount('attendance_days', 1);
    }

    public function test_reprocess_dispatches_processing_job(): void
    {
        $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        Queue::assertPushed(ProcessImportBatchJob::class, function ($job) {
            return $job->batch->id === $this->batch->id;
        });
    }

    public function test_reprocess_resets_batch_status(): void
    {
        $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $this->batch->refresh();
        $this->assertEquals('processing', $this->batch->status);
        $this->assertNull($this->batch->processed_at);
    }

    public function test_reprocess_returns_correct_counts(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'is_manually_edited' => false,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $response->assertJsonPath('deleted_attendance_days', 1)
            ->assertJsonPath('groups_to_process', 1);
    }

    public function test_reprocess_handles_multiple_employee_date_pairs(): void
    {
        $emp2 = Employee::factory()->create();

        RawLog::factory()->create([
            'employee_id' => $emp2->id,
            'import_batch_id' => $this->batch->id,
            'check_time' => '2026-01-15 08:00:00',
            'date_reference' => '2026-01-15',
        ]);

        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'is_manually_edited' => false,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
            'date_reference' => '2026-01-15',
            'is_manually_edited' => false,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $response->assertJsonPath('deleted_attendance_days', 2)
            ->assertJsonPath('groups_to_process', 2);
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_manager_cannot_reprocess_batch(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_reprocess(): void
    {
        $response = $this->postJson("/api/import/{$this->batch->id}/reprocess");

        $response->assertStatus(401);
    }

    public function test_reprocess_with_no_attendance_days_still_dispatches(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->postJson("/api/import/{$this->batch->id}/reprocess");

        $response->assertOk()
            ->assertJsonPath('deleted_attendance_days', 0)
            ->assertJsonPath('groups_to_process', 1);

        Queue::assertPushed(ProcessImportBatchJob::class);
    }
}
