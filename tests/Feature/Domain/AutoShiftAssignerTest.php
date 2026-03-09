<?php

namespace Tests\Feature\Domain;

use App\Domain\Attendance\AttendanceCalculator;
use App\Domain\Attendance\AttendanceEngine;
use App\Domain\Attendance\AutoShiftAssigner;
use App\Domain\Attendance\LateCalculator;
use App\Domain\Attendance\LogReducer;
use App\Domain\Attendance\LunchAnalyzer;
use App\Domain\Attendance\OvertimeCalculator;
use App\Domain\Attendance\ScheduleResolver;
use App\Domain\Attendance\ShiftResolver;
use App\Domain\Attendance\WorkTimeCalculator;
use App\Jobs\ProcessAttendanceDayJob;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\ImportBatch;
use App\Models\RawLog;
use App\Models\Shift;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoShiftAssignerTest extends TestCase
{
    use RefreshDatabase;

    private AutoShiftAssigner $assigner;

    private AttendanceEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assigner = new AutoShiftAssigner;

        $this->engine = new AttendanceEngine(
            new LogReducer,
            new ScheduleResolver,
            new ShiftResolver,
            new AttendanceCalculator(
                new LunchAnalyzer,
                new WorkTimeCalculator,
                new LateCalculator,
                new OvertimeCalculator,
            ),
            new AutoShiftAssigner,
        );
    }

    /**
     * Create historical raw_logs for an employee on days before the given date.
     * Each day gets a single check-in at the specified time.
     */
    private function createHistoricalLogs(Employee $employee, string $time, int $days, string $beforeDate): void
    {
        $batch = ImportBatch::factory()->create();

        for ($i = $days; $i >= 1; $i--) {
            $date = Carbon::parse($beforeDate)->subDays($i);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }
    }

    // ── AutoShiftAssigner unit tests ──────────────────────────

    public function test_resolves_shift_when_checkin_matches_start_time(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'is_active' => true,
        ]);

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 08:05:00'),
            30,
            1,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_resolves_shift_when_checkin_is_before_start_within_tolerance(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 07:40:00'),
            30,
            1,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_returns_null_when_checkin_outside_tolerance(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 12:00:00'),
            30,
            1,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_picks_closest_shift_when_multiple_match(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'name' => 'Matutino',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'is_active' => true,
        ]);

        $vespertino = Shift::factory()->create([
            'name' => 'Vespertino',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'is_active' => true,
        ]);

        // With historical data at 08:05, regularity favors Vespertino (08:00)
        $this->createHistoricalLogs($employee, '08:05', 3, '2026-01-15');

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 08:05:00'),
            30,
        );

        $this->assertNotNull($result);
        $this->assertEquals($vespertino->id, $result->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $vespertino->id,
        ]);
    }

    public function test_ignores_inactive_shifts(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => false,
        ]);

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 08:00:00'),
            30,
            1,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_returns_null_when_no_shifts_exist(): void
    {
        $employee = Employee::factory()->create();

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 08:00:00'),
            30,
            1,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_resolves_night_shift_correctly(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'name' => 'Nocturno',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
            'is_active' => true,
        ]);

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 21:50:00'),
            30,
            1,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_persists_assignment_when_shift_matched(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 08:00:00'),
            30,
            1,
        );

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    // ── Integration: AttendanceEngine with auto-assign ────────

    public function test_engine_auto_assigns_when_enabled(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'is_active' => true,
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:05:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 17:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process(
            $logs,
            $employee->id,
            Carbon::parse('2026-01-15'),
            60,
            true,
            30,
            autoAssignMinDays: 1,
        );

        $this->assertEquals('present', $result->status);
        $this->assertNotNull($result->shift);
        $this->assertEquals($shift->id, $result->shift->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_engine_does_not_auto_assign_when_disabled(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:05:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 17:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process(
            $logs,
            $employee->id,
            Carbon::parse('2026-01-15'),
            60,
            false,
        );

        $this->assertNull($result->shift);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_engine_skips_auto_assign_when_shift_already_assigned(): void
    {
        $employee = Employee::factory()->create();
        $existing = Shift::factory()->create([
            'name' => 'Existing',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        Shift::factory()->create([
            'name' => 'Other',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $existing->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 14:00:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 22:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process(
            $logs,
            $employee->id,
            Carbon::parse('2026-01-15'),
            60,
            true,
            30,
            autoAssignMinDays: 1,
        );

        $this->assertEquals($existing->id, $result->shift->id);
        $this->assertDatabaseCount('employee_shift_assignments', 1);
    }

    public function test_engine_calculates_late_after_auto_assign(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'is_active' => true,
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:25:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 17:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process(
            $logs,
            $employee->id,
            Carbon::parse('2026-01-15'),
            60,
            true,
            30,
            autoAssignMinDays: 1,
        );

        $this->assertNotNull($result->shift);
        $this->assertEquals(25, $result->lateMinutes);
    }

    // ── Integration: ProcessAttendanceDayJob with auto-assign ─

    public function test_job_auto_assigns_shift_using_system_settings(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'is_active' => true,
        ]);

        SystemSetting::query()->where('key', 'auto_assign_shift')->update(['value' => 'true']);
        SystemSetting::query()->where('key', 'auto_assign_tolerance_minutes')->update(['value' => '30']);
        SystemSetting::query()->where('key', 'auto_assign_min_days')->update(['value' => '1']);

        $batch = ImportBatch::factory()->create();
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 08:03:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 17:00:00',
            'date_reference' => '2026-01-15',
        ]);

        $job = new ProcessAttendanceDayJob($employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertNotNull($day);
        $this->assertEquals($shift->id, $day->shift_id);
        $this->assertEquals('present', $day->status);

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_job_does_not_auto_assign_when_setting_disabled(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        SystemSetting::query()->where('key', 'auto_assign_shift')->update(['value' => 'false']);

        $batch = ImportBatch::factory()->create();
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 08:03:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 17:00:00',
            'date_reference' => '2026-01-15',
        ]);

        $job = new ProcessAttendanceDayJob($employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertNull($day->shift_id);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_job_uses_custom_tolerance_from_settings(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);

        SystemSetting::query()->where('key', 'auto_assign_shift')->update(['value' => 'true']);
        SystemSetting::query()->where('key', 'auto_assign_tolerance_minutes')->update(['value' => '5']);
        SystemSetting::query()->where('key', 'auto_assign_min_days')->update(['value' => '1']);

        $batch = ImportBatch::factory()->create();
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 08:20:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 17:00:00',
            'date_reference' => '2026-01-15',
        ]);

        $job = new ProcessAttendanceDayJob($employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        // Tolerance is only 5 min; 08:20 is 20 min away — should NOT match
        $day = AttendanceDay::first();
        $this->assertNull($day->shift_id);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    // ── Existing assignment guard tests ───────────────────────

    public function test_skips_assignment_when_employee_already_has_active_assignment(): void
    {
        $employee = Employee::factory()->create();
        $existingShift = Shift::factory()->create([
            'name' => 'Jornada Completa',
            'start_time' => '07:00',
            'end_time' => '17:00',
            'is_active' => true,
        ]);
        Shift::factory()->create([
            'name' => 'Vespertino',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $existingShift->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        // Check-in near Vespertino, but employee already has Jornada Completa
        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 14:05:00'),
            30,
            1,
        );

        $this->assertNotNull($result);
        $this->assertEquals($existingShift->id, $result->id);
        // Should NOT create a second assignment
        $this->assertDatabaseCount('employee_shift_assignments', 1);
    }

    public function test_does_not_create_duplicate_on_reprocess(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '14:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        // First call creates assignment
        $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 14:05:00'),
            30,
            1,
        );

        $this->assertDatabaseCount('employee_shift_assignments', 1);

        // Second call (reprocess) should NOT create duplicate
        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 14:05:00'),
            30,
            1,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
        $this->assertDatabaseCount('employee_shift_assignments', 1);
    }

    public function test_correctly_picks_between_two_close_shifts(): void
    {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        $shift9 = Shift::factory()->create([
            'name' => 'Turno 9am',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);
        $shift10 = Shift::factory()->create([
            'name' => 'Turno 10am',
            'start_time' => '10:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Employee 1 checks in at 09:05 — should match 9am shift
        $r1 = $this->assigner->resolve(
            $employee1->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:05:00'),
            30,
            1,
        );

        // Employee 2 checks in at 10:10 — should match 10am shift
        $r2 = $this->assigner->resolve(
            $employee2->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 10:10:00'),
            30,
            1,
        );

        $this->assertEquals($shift9->id, $r1->id);
        $this->assertEquals($shift10->id, $r2->id);

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee1->id,
            'shift_id' => $shift9->id,
        ]);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee2->id,
            'shift_id' => $shift10->id,
        ]);
    }

    public function test_assigns_to_closest_shift_in_overlap_zone(): void
    {
        $employee = Employee::factory()->create();

        $shift9 = Shift::factory()->create([
            'name' => 'Turno 9am',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);
        Shift::factory()->create([
            'name' => 'Turno 10am',
            'start_time' => '10:00',
            'end_time' => '19:00',
            'is_active' => true,
        ]);

        // Check-in at 09:25 — in 9am window (09:00±30 = 8:30-9:30)
        // but NOT in 10am window (10:00±30 = 9:30-10:30, 09:25 < 9:30)
        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:25:00'),
            30,
            1,
        );

        $this->assertEquals($shift9->id, $result->id);
    }

    // ── Regularity-based assignment tests ─────────────────────

    public function test_does_not_assign_when_not_enough_days(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // Only 1 historical day + current = 2 days, need 3
        $this->createHistoricalLogs($employee, '09:05', 1, '2026-01-15');

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:02:00'),
            30,
            3,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_assigns_when_min_days_threshold_exactly_met(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // 2 historical days + current = 3 days, need exactly 3
        $this->createHistoricalLogs($employee, '09:05', 2, '2026-01-15');

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:02:00'),
            30,
            3,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_assigns_regular_employee_with_consistent_checkins(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // 5 historical days all near 09:00 — 100% regularity
        $this->createHistoricalLogs($employee, '09:05', 5, '2026-01-15');

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 08:58:00'),
            30,
            3,
            70,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_does_not_assign_irregular_employee(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // Create scattered historical check-ins (different times each day)
        $batch = ImportBatch::factory()->create();
        $scatteredTimes = ['07:00', '12:30', '17:05', '10:45', '14:20'];

        foreach ($scatteredTimes as $i => $time) {
            $date = Carbon::parse('2026-01-15')->subDays($i + 1);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        // Current day also scattered — at 15:00
        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 15:00:00'),
            30,
            3,
            70,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_does_not_assign_two_shift_rotation_employee(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'name' => 'Matutino',
            'start_time' => '09:00',
            'end_time' => '14:00',
            'is_active' => true,
        ]);
        Shift::factory()->create([
            'name' => 'Vespertino',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'is_active' => true,
        ]);

        // Alternating between AM and PM shifts — no single shift reaches 70%
        $batch = ImportBatch::factory()->create();
        $alternatingTimes = ['09:05', '14:02', '09:10', '14:05', '09:00', '14:10'];

        foreach ($alternatingTimes as $i => $time) {
            $date = Carbon::parse('2026-01-15')->subDays($i + 1);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:05:00'),
            30,
            3,
            70,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_assigns_when_regularity_percent_threshold_met(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // 7 of 10 days near 09:00 = 70%, 3 days outliers
        $batch = ImportBatch::factory()->create();
        $times = ['09:05', '09:10', '09:00', '12:30', '09:08', '15:00', '09:02', '09:15', '09:03'];

        foreach ($times as $i => $time) {
            $date = Carbon::parse('2026-01-15')->subDays($i + 1);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        // Current day near 09:00 — total: 8/10 = 80% >= 70%
        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:04:00'),
            30,
            3,
            70,
        );

        $this->assertNotNull($result);
        $this->assertEquals($shift->id, $result->id);
    }

    public function test_does_not_assign_when_regularity_below_threshold(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // 4 of 10 days near 09:00 = 40%, well below 70%
        $batch = ImportBatch::factory()->create();
        $times = ['09:05', '12:30', '15:00', '09:10', '17:00', '11:00', '09:00', '14:30', '16:00'];

        foreach ($times as $i => $time) {
            $date = Carbon::parse('2026-01-15')->subDays($i + 1);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        // Current day near 09:00 — total: 4/10 = 40% < 70%
        $result = $this->assigner->resolve(
            $employee->id,
            Carbon::parse('2026-01-15'),
            Carbon::parse('2026-01-15 09:02:00'),
            30,
            3,
            70,
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_job_respects_min_days_and_regularity_settings(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        SystemSetting::query()->where('key', 'auto_assign_shift')->update(['value' => 'true']);
        SystemSetting::query()->where('key', 'auto_assign_tolerance_minutes')->update(['value' => '30']);
        SystemSetting::query()->where('key', 'auto_assign_min_days')->update(['value' => '3']);
        SystemSetting::query()->where('key', 'auto_assign_regularity_percent')->update(['value' => '70']);

        $batch = ImportBatch::factory()->create();

        // Create 3 historical days of consistent check-ins
        $this->createHistoricalLogs($employee, '09:05', 3, '2026-01-15');

        // Current day
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 09:03:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 18:00:00',
            'date_reference' => '2026-01-15',
        ]);

        $job = new ProcessAttendanceDayJob($employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertNotNull($day);
        $this->assertEquals($shift->id, $day->shift_id);

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_job_skips_assignment_for_irregular_employee(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        SystemSetting::query()->where('key', 'auto_assign_shift')->update(['value' => 'true']);
        SystemSetting::query()->where('key', 'auto_assign_tolerance_minutes')->update(['value' => '30']);
        SystemSetting::query()->where('key', 'auto_assign_min_days')->update(['value' => '3']);
        SystemSetting::query()->where('key', 'auto_assign_regularity_percent')->update(['value' => '70']);

        $batch = ImportBatch::factory()->create();

        // Create scattered historical check-ins
        $scatteredTimes = ['07:00', '12:30', '17:05'];
        foreach ($scatteredTimes as $i => $time) {
            $date = Carbon::parse('2026-01-15')->subDays($i + 1);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        // Current day
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 15:00:00',
            'date_reference' => '2026-01-15',
        ]);
        RawLog::factory()->create([
            'employee_id' => $employee->id,
            'import_batch_id' => $batch->id,
            'check_time' => '2026-01-15 22:00:00',
            'date_reference' => '2026-01-15',
        ]);

        $job = new ProcessAttendanceDayJob($employee->id, '2026-01-15');
        app()->call([$job, 'handle']);

        $day = AttendanceDay::first();
        $this->assertNotNull($day);
        $this->assertNull($day->shift_id);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_engine_regularity_with_historical_logs(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // Create consistent historical raw_logs in DB
        $this->createHistoricalLogs($employee, '09:05', 4, '2026-01-15');

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 09:02:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 18:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process(
            $logs,
            $employee->id,
            Carbon::parse('2026-01-15'),
            60,
            true,
            30,
            autoAssignMinDays: 3,
        );

        $this->assertNotNull($result->shift);
        $this->assertEquals($shift->id, $result->shift->id);
        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_engine_rejects_irregular_employee(): void
    {
        $employee = Employee::factory()->create();
        Shift::factory()->create([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ]);

        // Create scattered historical raw_logs
        $batch = ImportBatch::factory()->create();
        $scatteredTimes = ['07:00', '12:30', '17:05', '10:45'];
        foreach ($scatteredTimes as $i => $time) {
            $date = Carbon::parse('2026-01-15')->subDays($i + 1);
            RawLog::factory()->create([
                'employee_id' => $employee->id,
                'import_batch_id' => $batch->id,
                'check_time' => $date->copy()->setTimeFromTimeString($time),
                'date_reference' => $date,
            ]);
        }

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 15:00:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 22:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process(
            $logs,
            $employee->id,
            Carbon::parse('2026-01-15'),
            60,
            true,
            30,
            autoAssignMinDays: 3,
        );

        $this->assertNull($result->shift);
        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }
}
