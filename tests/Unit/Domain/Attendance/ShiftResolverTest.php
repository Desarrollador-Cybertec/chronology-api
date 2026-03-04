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

        $resolved = $this->resolver->resolve($employee->id, Carbon::parse('2026-02-15'));

        $this->assertEquals($newShift->id, $resolved->id);
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
