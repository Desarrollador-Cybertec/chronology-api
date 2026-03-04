<?php

namespace Tests\Feature\Employee;

use App\Models\Employee;
use App\Models\EmployeeShiftAssignment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_employees(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->count(5)->create();

        $response = $this->actingAs($user)->getJson('/api/employees');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'internal_id', 'first_name', 'last_name',
                        'full_name', 'department', 'position', 'is_active',
                    ],
                ],
            ]);
    }

    public function test_manager_can_list_employees(): void
    {
        $user = User::factory()->manager()->create();
        Employee::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/employees');

        $response->assertOk();
    }

    public function test_employees_are_ordered_by_last_name(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['last_name' => 'Zapata']);
        Employee::factory()->create(['last_name' => 'Acosta']);

        $response = $this->actingAs($user)->getJson('/api/employees');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Acosta', $data[0]['last_name']);
        $this->assertEquals('Zapata', $data[1]['last_name']);
    }

    public function test_employees_include_shift_assignments_when_loaded(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();
        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
        ]);

        $response = $this->actingAs($user)->getJson('/api/employees');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'shift_assignments',
                    ],
                ],
            ]);
    }

    public function test_superadmin_can_show_employee(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $employee->id)
            ->assertJsonPath('data.internal_id', $employee->internal_id);
    }

    public function test_superadmin_can_update_employee(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/employees/{$employee->id}", [
            'first_name' => 'Carlos',
            'last_name' => 'Mendoza',
            'department' => 'IT',
            'position' => 'Developer',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Carlos')
            ->assertJsonPath('data.last_name', 'Mendoza')
            ->assertJsonPath('data.department', 'IT');
    }

    public function test_manager_cannot_update_employee(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/employees/{$employee->id}", [
            'first_name' => 'Hack',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_toggle_employee_active(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->patchJson("/api/employees/{$employee->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('employees', [
            'id' => $employee->id,
            'is_active' => false,
        ]);
    }

    public function test_toggle_reactivates_inactive_employee(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->inactive()->create();

        $response = $this->actingAs($user)->patchJson("/api/employees/{$employee->id}/toggle-active");

        $response->assertOk()
            ->assertJsonPath('is_active', true);
    }

    public function test_manager_cannot_toggle_employee_active(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->patchJson("/api/employees/{$employee->id}/toggle-active");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_employees(): void
    {
        $response = $this->getJson('/api/employees');

        $response->assertStatus(401);
    }
}
