<?php

namespace Tests\Feature\Jobs;

use App\Jobs\AssignWeeklyShiftsJob;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\Shift;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignWeeklyShiftsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::insert([
            ['key' => 'auto_assign_shift', 'value' => 'true'],
            ['key' => 'auto_assign_tolerance_minutes', 'value' => '30'],
            ['key' => 'auto_assign_regularity_percent', 'value' => '70'],
            ['key' => 'auto_assign_min_days', 'value' => '3'],
        ]);
    }

    private function createWeekLogs(
        Employee $employee,
        ImportBatch $batch,
        string $time,
        string $weekStartDate,
        array $days = [0, 1, 2, 3, 4],
    ): void {
        $weekStart = Carbon::parse($weekStartDate)->startOfWeek(Carbon::MONDAY);

        foreach ($days as $dayOffset) {
            $date = $weekStart->copy()->addDays($dayOffset);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }
    }

    public function test_assigns_shift_for_week_with_consistent_checkins(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Mon-Fri of ISO week, all checking in at 08:05
        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 1);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_does_not_assign_when_checkins_are_irregular(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $weekStart = Carbon::parse('2026-01-12')->startOfWeek(Carbon::MONDAY);
        $scatteredTimes = ['07:00', '12:30', '15:00', '10:45', '06:00'];

        foreach ($scatteredTimes as $i => $time) {
            $date = $weekStart->copy()->addDays($i);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_assigns_correct_shift_among_multiple(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'name' => 'Matutino',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'is_active' => true,
        ]);
        $vespertino = Shift::factory()->create([
            'name' => 'Vespertino',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        // All check-ins near 14:00
        $this->createWeekLogs($employee, $batch, '14:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $vespertino->id,
        ]);
    }

    public function test_creates_separate_assignments_per_week(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Week 1: Jan 12-16
        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');
        // Week 2: Jan 19-23
        $this->createWeekLogs($employee, $batch, '08:02', '2026-01-19');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 2);
    }

    public function test_processes_multiple_employees_independently(): void
    {
        $emp1 = Employee::factory()->create();
        $emp2 = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $this->createWeekLogs($emp1, $batch, '08:05', '2026-01-12');
        $this->createWeekLogs($emp2, $batch, '08:10', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 2);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $emp1->id,
            'shift_id' => $shift->id,
        ]);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $emp2->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_skips_week_when_employee_already_has_assignment(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        $existingShift = Shift::factory()->create([
            'name' => 'Existing',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'is_active' => true,
        ]);
        Shift::factory()->create([
            'name' => 'Other',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $existingShift->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        // Should NOT create a second assignment
        $this->assertDatabaseCount('employee_shift_assignments', 1);
    }

    public function test_does_not_assign_when_auto_assign_disabled(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        SystemSetting::query()->where('key', 'auto_assign_shift')->update(['value' => 'false']);

        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_ignores_inactive_shifts(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => false,
        ]);

        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_returns_early_when_no_active_shifts_exist(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();

        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_uses_custom_tolerance_from_settings(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Tolerance = 5 min; check-in at 08:20 is 20 min away
        SystemSetting::query()->where('key', 'auto_assign_tolerance_minutes')->update(['value' => '5']);

        $this->createWeekLogs($employee, $batch, '08:20', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_uses_regularity_percent_from_settings(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Require 100% regularity
        SystemSetting::query()->where('key', 'auto_assign_regularity_percent')->update(['value' => '100']);

        $weekStart = Carbon::parse('2026-01-12')->startOfWeek(Carbon::MONDAY);
        // 4 near 08:00, 1 outlier → 80% regularity < 100%
        $times = ['08:05', '08:02', '08:10', '08:03', '12:00'];
        foreach ($times as $i => $time) {
            $date = $weekStart->copy()->addDays($i);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_detects_work_days_from_actual_checkins(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Only Mon, Wed, Fri (days 0, 2, 4 from monday)
        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12', [0, 2, 4]);

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $assignment = EmployeeShiftAssignment::first();
        $this->assertNotNull($assignment);
        // Mon=1, Wed=3, Fri=5 (Carbon dayOfWeek)
        $this->assertEquals([1, 3, 5], $assignment->work_days);
    }

    public function test_assignment_has_correct_week_boundaries(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Week of Jan 12-16 (Mon-Fri)
        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $assignment = EmployeeShiftAssignment::first();
        $this->assertNotNull($assignment);
        $this->assertEquals('2026-01-12', $assignment->effective_date->format('Y-m-d'));
        $this->assertEquals('2026-01-16', $assignment->end_date->format('Y-m-d'));
    }

    public function test_does_not_create_duplicate_on_reprocess(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $this->createWeekLogs($employee, $batch, '08:05', '2026-01-12');

        // First run
        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();
        $this->assertDatabaseCount('employee_shift_assignments', 1);

        // Second run (reprocess)
        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();
        $this->assertDatabaseCount('employee_shift_assignments', 1);
    }

    public function test_night_shift_detection(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        $shift = Shift::factory()->create([
            'name' => 'Nocturno',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
            'is_active' => true,
        ]);

        $this->createWeekLogs($employee, $batch, '21:55', '2026-01-12');

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_january_produces_approximately_five_assignments(): void
    {
        $employee = Employee::factory()->create();
        $batch = ImportBatch::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        // Create logs for all weekdays in January 2026
        $date = Carbon::parse('2026-01-01');
        $endOfJanuary = Carbon::parse('2026-01-31');

        while ($date->lte($endOfJanuary)) {
            if ($date->isWeekday()) {
                RawLog::factory()->create([
                    'employee_id' => $employee->id,
                    'import_batch_id' => $batch->id,
                    'check_time' => $date->copy()->setTimeFromTimeString('08:05'),
                    'date_reference' => $date->copy(),
                ]);
            }
            $date->addDay();
        }

        $job = new AssignWeeklyShiftsJob($batch);
        $job->handle();

        $count = EmployeeShiftAssignment::where('employee_id', $employee->id)->count();
        $this->assertEquals(5, $count);
    }
}
