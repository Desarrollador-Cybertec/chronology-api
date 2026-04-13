<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\AttendanceEngine;
use App\Domain\Attendance\ScheduleResolver;
use App\Models\Employee;
use App\Models\EmployeeScheduleException;
use App\Models\EmployeeShiftAssignment;
use App\Models\RawLog;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleResolverTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ScheduleResolver;
    }

    public function test_weekday_is_working_day_by_default(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        // 2026-01-15 is a Thursday (day 4)
        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertTrue($result->isWorkingDay);
        $this->assertNotNull($result->shift);
        $this->assertEquals($shift->id, $result->shift->id);
        $this->assertEquals('assignment', $result->source);
    }

    public function test_saturday_is_rest_day_by_default(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        // 2026-01-17 is a Saturday (day 6)
        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-17'));

        $this->assertFalse($result->isWorkingDay);
        $this->assertNull($result->shift);
        $this->assertEquals('assignment', $result->source);
    }

    public function test_sunday_is_rest_day_by_default(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        // 2026-01-18 is a Sunday (day 0)
        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-18'));

        $this->assertFalse($result->isWorkingDay);
        $this->assertNull($result->shift);
    }

    public function test_custom_work_days_include_saturday(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'work_days' => [1, 2, 3, 4, 5, 6],
        ]);

        // 2026-01-17 is a Saturday (day 6)
        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-17'));

        $this->assertTrue($result->isWorkingDay);
        $this->assertNotNull($result->shift);
    }

    public function test_exception_overrides_rest_day_to_working(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();
        $alternateShift = Shift::factory()->create(['name' => 'Turno Especial']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        // Saturday exception: work this day with alternate shift
        EmployeeScheduleException::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-01-17',
            'shift_id' => $alternateShift->id,
            'is_working_day' => true,
            'reason' => 'Inventario',
        ]);

        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-17'));

        $this->assertTrue($result->isWorkingDay);
        $this->assertEquals($alternateShift->id, $result->shift->id);
        $this->assertEquals('exception', $result->source);
    }

    public function test_exception_overrides_working_day_to_rest(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        // Wednesday exception: rest day
        EmployeeScheduleException::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-01-14',
            'is_working_day' => false,
            'reason' => 'Permiso especial',
        ]);

        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-14'));

        $this->assertFalse($result->isWorkingDay);
        $this->assertNull($result->shift);
        $this->assertEquals('exception', $result->source);
    }

    public function test_exception_working_day_without_shift_falls_back(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
        ]);

        // Exception says it's a working day but no specific shift → shift is null from exception
        EmployeeScheduleException::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-01-17',
            'shift_id' => null,
            'is_working_day' => true,
            'reason' => 'Trabajar sábado',
        ]);

        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-17'));

        $this->assertTrue($result->isWorkingDay);
        $this->assertNull($result->shift);
        $this->assertEquals('exception', $result->source);
    }

    public function test_no_assignment_returns_working_day_with_null_shift(): void
    {
        $employee = Employee::factory()->create();

        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertTrue($result->isWorkingDay);
        $this->assertNull($result->shift);
        $this->assertEquals('none', $result->source);
    }

    public function test_expired_assignment_not_used(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => '2026-01-10',
        ]);

        $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertTrue($result->isWorkingDay);
        $this->assertNull($result->shift);
        $this->assertEquals('none', $result->source);
    }

    public function test_resolves_correct_concurrent_shift_by_day_for_schedule(): void
    {
        $employee = Employee::factory()->create();
        $shiftA = Shift::factory()->create(['name' => 'Turno A']);
        $shiftB = Shift::factory()->create(['name' => 'Turno B']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftA->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
            'work_days' => [1, 2, 3],
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
            'work_days' => [4, 5, 6],
        ]);

        // 2026-01-13 is a Tuesday (day 2) → shiftA
        $resultA = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-13'));
        $this->assertTrue($resultA->isWorkingDay);
        $this->assertEquals($shiftA->id, $resultA->shift->id);

        // 2026-01-15 is a Thursday (day 4) → shiftB
        $resultB = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));
        $this->assertTrue($resultB->isWorkingDay);
        $this->assertEquals($shiftB->id, $resultB->shift->id);

        // 2026-01-18 is a Sunday (day 0) → rest (no matching assignment)
        $resultRest = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-18'));
        $this->assertFalse($resultRest->isWorkingDay);
        $this->assertNull($resultRest->shift);
    }

    public function test_engine_returns_rest_for_non_working_day(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        $engine = app(AttendanceEngine::class);

        $logs = collect([
            RawLog::factory()->make([
                'employee_id' => $employee->id,
                'check_time' => '2026-01-17 08:00:00',
                'date_reference' => '2026-01-17',
            ]),
        ]);

        // Saturday — should return rest
        $result = $engine->process($logs, $employee->id, Carbon::parse('2026-01-17'));

        $this->assertEquals('rest', $result->status);
        $this->assertEquals(0, $result->workedMinutes);
        $this->assertNull($result->shift);
    }
}
