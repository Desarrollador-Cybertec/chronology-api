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

        // Check-in at 08:05 — closest to Vespertino (08:00) vs Matutino (06:00)
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
}
