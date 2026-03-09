<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessAttendanceDayJob;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\Shift;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessAttendanceDayJobTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = Employee::factory()->create();
        $this->shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'tolerance_minutes' => 10,
            'overtime_enabled' => false,
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);
    }

    private function createRawLogs(array $times): void
    {
        $batch = ImportBatch::factory()->create();

        foreach ($times as $time) {
            RawLog::factory()->create([
                'employee_id' => $this->employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => "2026-01-15 {$time}",
                'date_reference' => '2026-01-15',
            ]);
        }
    }

    public function test_creates_attendance_day_for_present_employee(): void
    {
        $this->createRawLogs(['08:05:00', '17:02:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $this->assertDatabaseCount('attendance_days', 1);

        $day = AttendanceDay::first();
        $this->assertEquals($this->employee->id, $day->employee_id);
        $this->assertEquals('2026-01-15', $day->date_reference->format('Y-m-d'));
        $this->assertEquals('present', $day->status);
        $this->assertEquals($this->shift->id, $day->shift_id);
    }

    public function test_calculates_worked_minutes(): void
    {
        $this->createRawLogs(['08:00:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals(540, $day->worked_minutes); // 9 hours = 540 min
    }

    public function test_calculates_late_minutes(): void
    {
        $this->createRawLogs(['08:20:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals(20, $day->late_minutes);
    }

    public function test_no_late_within_tolerance(): void
    {
        $this->createRawLogs(['08:05:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals(0, $day->late_minutes);
    }

    public function test_creates_absent_when_no_logs(): void
    {
        // No raw_logs created

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals($this->employee->id, $day->employee_id);
        $this->assertEquals('2026-01-15', $day->date_reference->format('Y-m-d'));
        $this->assertEquals('absent', $day->status);
        $this->assertEquals(0, $day->worked_minutes);
    }

    public function test_updates_existing_attendance_day(): void
    {
        // Create initial attendance day
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'shift_id' => $this->shift->id,
            'status' => 'absent',
            'worked_minutes' => 0,
        ]);

        $this->createRawLogs(['08:00:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $this->assertDatabaseCount('attendance_days', 1);
        $day = AttendanceDay::first();
        $this->assertEquals('present', $day->status);
        $this->assertEquals(540, $day->worked_minutes);
    }

    public function test_uses_system_setting_noise_window(): void
    {
        SystemSetting::query()->where('key', 'noise_window_minutes')->update(['value' => '5']);

        // Two check-ins within 5 minutes — should be reduced to one group
        $this->createRawLogs(['08:00:00', '08:03:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals('present', $day->status);
        $this->assertEquals(540, $day->worked_minutes);
    }

    public function test_sets_first_check_and_last_check(): void
    {
        $this->createRawLogs(['08:05:00', '12:30:00', '17:10:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals('2026-01-15 08:05:00', $day->first_check_in->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-01-15 17:10:00', $day->last_check_out->format('Y-m-d H:i:s'));
    }

    public function test_handles_single_punch(): void
    {
        $this->createRawLogs(['08:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertEquals('incomplete', $day->status);
    }

    public function test_calculates_overtime_when_shift_allows(): void
    {
        $shift = Shift::factory()->withOvertime()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
        ]);

        EmployeeShiftAssignment::query()
            ->where('employee_id', $this->employee->id)
            ->delete();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $this->createRawLogs(['08:00:00', '19:30:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertGreaterThan(0, $day->overtime_minutes);
        $this->assertGreaterThan(0, $day->overtime_diurnal_minutes);
    }

    public function test_increments_batch_processed_rows_when_import_batch_id_provided(): void
    {
        $batch = ImportBatch::factory()->create([
            'status' => 'processing',
            'total_rows' => 3,
            'processed_rows' => 0,
        ]);

        $this->createRawLogs(['08:00:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15', $batch->id);
        app()->call([$job, 'handle']);

        $batch->refresh();
        $this->assertEquals(1, $batch->processed_rows);
        $this->assertEquals('processing', $batch->status);
    }

    public function test_marks_batch_completed_when_last_job_finishes(): void
    {
        $batch = ImportBatch::factory()->create([
            'status' => 'processing',
            'total_rows' => 1,
            'processed_rows' => 0,
        ]);

        $this->createRawLogs(['08:00:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15', $batch->id);
        app()->call([$job, 'handle']);

        $batch->refresh();
        $this->assertEquals(1, $batch->processed_rows);
        $this->assertEquals('completed', $batch->status);
        $this->assertNotNull($batch->processed_at);
    }

    public function test_does_not_update_batch_when_no_import_batch_id(): void
    {
        $batch = ImportBatch::factory()->create([
            'status' => 'processing',
            'total_rows' => 5,
            'processed_rows' => 0,
        ]);

        $this->createRawLogs(['08:00:00', '17:00:00']);

        $job = new ProcessAttendanceDayJob($this->employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $batch->refresh();
        $this->assertEquals(0, $batch->processed_rows);
        $this->assertEquals('processing', $batch->status);
    }
}
