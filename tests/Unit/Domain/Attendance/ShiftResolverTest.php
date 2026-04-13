<?php

namespace Tests\Unit\Domain\Attendance;

use App\Domain\Attendance\ShiftResolver;
use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftResolverTest extends TestCase
{
    use RefreshDatabase;

    private ShiftResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ShiftResolver;
    }

    public function test_resolves_active_shift(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create(['name' => 'Matutino']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertNotNull($resolved);
        $this->assertEquals($shift->id, $resolved->id);
    }

    public function test_returns_null_when_no_assignment(): void
    {
        $employee = Employee::factory()->create();

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertNull($resolved);
    }

    public function test_returns_most_recent_assignment(): void
    {
        $employee = Employee::factory()->create();
        $oldShift = Shift::factory()->create(['name' => 'Matutino-Old']);
        $newShift = Shift::factory()->create(['name' => 'Vespertino-New']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $oldShift->id,
            'effective_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $newShift->id,
            'effective_date' => '2026-02-01',
            'end_date' => null,
        ]);

        // 2026-02-16 is a Monday (day 1) — within default work_days [1,2,3,4,5]
        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-02-16'));

        $this->assertEquals($newShift->id, $resolved->id);
    }

    public function test_resolves_correct_concurrent_shift_by_work_day(): void
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
        $this->assertEquals($shiftA->id, $this->resolver->resolve($employee->id, Carbon::parse('2026-01-13'))?->id);

        // 2026-01-15 is a Thursday (day 4) → shiftB
        $this->assertEquals($shiftB->id, $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'))?->id);

        // 2026-01-18 is a Sunday (day 0) → no match, null
        $this->assertNull($this->resolver->resolve($employee->id, Carbon::parse('2026-01-18')));
    }

    public function test_ignores_expired_assignments(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-02-15'));

        $this->assertNull($resolved);
    }

    public function test_ignores_future_assignments(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-03-01',
            'end_date' => null,
        ]);

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-02-15'));

        $this->assertNull($resolved);
    }

    public function test_resolves_assignment_on_exact_effective_date(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-15',
            'end_date' => null,
        ]);

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertNotNull($resolved);
        $this->assertEquals($shift->id, $resolved->id);
    }

    public function test_resolves_assignment_on_exact_end_date(): void
    {
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => '2026-01-15',
        ]);

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-01-15'));

        $this->assertNotNull($resolved);
        $this->assertEquals($shift->id, $resolved->id);
    }
}
