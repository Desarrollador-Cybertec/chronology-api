<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\AttendanceCalculator;
use App\Domain\Attendance\AttendanceDayBuilder;
use App\Domain\Attendance\AttendanceEngine;
use App\Domain\Attendance\AutoShiftAssigner;
use App\Domain\Attendance\LateCalculator;
use App\Domain\Attendance\LogReducer;
use App\Domain\Attendance\OvertimeCalculator;
use App\Domain\Attendance\ShiftResolver;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\RawLog;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceEngineTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new AttendanceEngine(
            new LogReducer,
            new ShiftResolver,
            new AttendanceCalculator(
                new AttendanceDayBuilder,
                new LateCalculator,
                new OvertimeCalculator,
            ),
            new AutoShiftAssigner,
        );
    }

    public function test_full_pipeline_present_on_time(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:00:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 17:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals(540, $result->workedMinutes);
        $this->assertEquals(0, $result->lateMinutes);
        $this->assertEquals(0, $result->overtimeMinutes);
        $this->assertNotNull($result->shift);
    }

    public function test_full_pipeline_late_arrival(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:30:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 17:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals(30, $result->lateMinutes);
    }

    public function test_full_pipeline_with_overtime(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->withOvertime()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:00:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 19:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals(660, $result->workedMinutes);
        $this->assertEquals(120, $result->overtimeMinutes);
    }

    public function test_absent_with_no_logs(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $result = $this->engine->process(collect(), $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('absent', $result->status);
        $this->assertEquals(0, $result->workedMinutes);
    }

    public function test_incomplete_with_single_log(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:05:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('incomplete', $result->status);
    }

    public function test_no_shift_assigned(): void
    {
        $employee = Employee::factory()->create();

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 08:00:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 17:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals(540, $result->workedMinutes);
        $this->assertNull($result->shift);
        $this->assertEquals(0, $result->lateMinutes);
        $this->assertEquals(0, $result->overtimeMinutes);
    }

    public function test_noise_reduction_in_pipeline(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create([
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make(['employee_id' => $employee->id, 'check_time' => '2026-01-15 08:00:00', 'date_reference' => '2026-01-15']),
            RawLog::factory()->make(['employee_id' => $employee->id, 'check_time' => '2026-01-15 08:02:00', 'date_reference' => '2026-01-15']),
            RawLog::factory()->make(['employee_id' => $employee->id, 'check_time' => '2026-01-15 08:05:00', 'date_reference' => '2026-01-15']),
            RawLog::factory()->make(['employee_id' => $employee->id, 'check_time' => '2026-01-15 17:00:00', 'date_reference' => '2026-01-15']),
            RawLog::factory()->make(['employee_id' => $employee->id, 'check_time' => '2026-01-15 17:03:00', 'date_reference' => '2026-01-15']),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals('08:00', $result->firstCheck->format('H:i'));
        $this->assertEquals('17:00', $result->lastCheck->format('H:i'));
    }

    public function test_night_shift_full_pipeline(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->nightShift()->withOvertime()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-15 22:00:00',
                'date_reference' => '2026-01-15',
            ]),
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-16 08:00:00',
                'date_reference' => '2026-01-15',
            ]),
        ]);

        $result = $this->engine->process($logs, $employee->id, Carbon::parse('2026-01-15'));

        $this->assertEquals('present', $result->status);
        $this->assertEquals(600, $result->workedMinutes);
        $this->assertGreaterThan(0, $result->overtimeMinutes);
        $this->assertEquals(120, $result->overtimeDiurnalMinutes);
        $this->assertEquals(0, $result->overtimeNocturnalMinutes);
    }
}
