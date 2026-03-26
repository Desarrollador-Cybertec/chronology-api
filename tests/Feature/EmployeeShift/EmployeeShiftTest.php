<?php

namespace Tests\Feature\EmployeeShift;

use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_employee_shifts(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();
        EmployeeShiftAssignment::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/shifts");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_can_list_employee_shifts(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/shifts");

        $response->assertOk();
    }

    public function test_superadmin_can_assign_shift_to_employee(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2025-01-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.effective_date', '2025-01-01');

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);
    }

    public function test_superadmin_can_assign_shift_with_end_date(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.end_date', '2025-06-30');
    }

    public function test_manager_cannot_create_employee_shift(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2025-01-01',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_show_employee_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $assignment = EmployeeShiftAssignment::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/employee-shifts/{$assignment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $assignment->id);
    }

    public function test_superadmin_can_update_employee_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $assignment = EmployeeShiftAssignment::factory()->create();
        $newShift = Shift::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/employee-shifts/{$assignment->id}", [
            'shift_id' => $newShift->id,
            'effective_date' => '2025-03-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.shift_id', $newShift->id)
            ->assertJsonPath('data.effective_date', '2025-03-01');
    }

    public function test_superadmin_can_delete_employee_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $assignment = EmployeeShiftAssignment::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/employee-shifts/{$assignment->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Asignación eliminada correctamente.');

        $this->assertDatabaseMissing('employee_shift_assignments', ['id' => $assignment->id]);
    }

    public function test_manager_cannot_delete_employee_shift(): void
    {
        $user = User::factory()->manager()->create();
        $assignment = EmployeeShiftAssignment::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/employee-shifts/{$assignment->id}");

        $response->assertStatus(403);
    }

    public function test_create_fails_with_nonexistent_employee(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => 9999,
            'shift_id' => $shift->id,
            'effective_date' => '2025-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_create_fails_with_nonexistent_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => 9999,
            'effective_date' => '2025-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('shift_id');
    }

    public function test_create_fails_when_end_date_before_effective_date(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'effective_date' => '2025-06-01',
            'end_date' => '2025-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_employee_shifts_index_returns_pagination_meta(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        EmployeeShiftAssignment::factory()->count(5)->create(['employee_id' => $employee->id]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/shifts");

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_employee_shifts_index_respects_per_page_parameter(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        EmployeeShiftAssignment::factory()->count(6)->create(['employee_id' => $employee->id]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/shifts?per_page=4");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 4)
            ->assertJsonCount(4, 'data');
    }

    public function test_employee_shifts_index_supports_page_navigation(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        EmployeeShiftAssignment::factory()->count(5)->create(['employee_id' => $employee->id]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/shifts?per_page=3&page=2");

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_store_closes_previous_open_assignment(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shiftA = Shift::factory()->create();
        $shiftB = Shift::factory()->create();

        $existing = EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftA->id,
            'effective_date' => '2025-01-01',
            'end_date' => null,
        ]);

        $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2025-06-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('employee_shift_assignments', [
            'id' => $existing->id,
            'end_date' => '2025-05-31',
        ]);

        $this->assertDatabaseHas('employee_shift_assignments', [
            'employee_id' => $employee->id,
            'shift_id' => $shiftB->id,
            'end_date' => null,
        ]);
    }

    public function test_store_does_not_close_assignment_starting_same_date(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shiftA = Shift::factory()->create();
        $shiftB = Shift::factory()->create();

        $existing = EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftA->id,
            'effective_date' => '2025-06-01',
            'end_date' => null,
        ]);

        $this->actingAs($user)->postJson('/api/employee-shifts', [
            'employee_id' => $employee->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2025-06-01',
        ])->assertStatus(201);

        $this->assertDatabaseHas('employee_shift_assignments', [
            'id' => $existing->id,
            'end_date' => null,
        ]);
    }

    public function test_superadmin_can_delete_all_employee_shifts(): void
    {
        $user = User::factory()->superadmin()->create();
        EmployeeShiftAssignment::factory()->count(5)->create();

        $response = $this->actingAs($user)->deleteJson('/api/employee-shifts');

        $response->assertOk()
            ->assertJsonPath('deleted_count', 5);

        $this->assertDatabaseCount('employee_shift_assignments', 0);
    }

    public function test_destroy_all_scoped_to_employee_ids(): void
    {
        $user = User::factory()->superadmin()->create();
        $employeeA = Employee::factory()->create();
        $employeeB = Employee::factory()->create();

        EmployeeShiftAssignment::factory()->count(3)->create(['employee_id' => $employeeA->id]);
        EmployeeShiftAssignment::factory()->count(2)->create(['employee_id' => $employeeB->id]);

        $response = $this->actingAs($user)->deleteJson('/api/employee-shifts', [
            'employee_ids' => [$employeeA->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('deleted_count', 3);

        $this->assertDatabaseCount('employee_shift_assignments', 2);
    }

    public function test_manager_cannot_delete_all_employee_shifts(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->deleteJson('/api/employee-shifts');

        $response->assertStatus(403);
    }
}
