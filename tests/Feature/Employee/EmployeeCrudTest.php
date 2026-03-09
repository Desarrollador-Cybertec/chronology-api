<?php

namespace Tests\Feature\Employee;

use App\Models\AttendanceDay;
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

    public function test_employees_are_ordered_alphabetically(): void
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

    public function test_employees_search_by_name(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Pérez']);
        Employee::factory()->create(['first_name' => 'María', 'last_name' => 'López']);
        Employee::factory()->create(['first_name' => 'Juan', 'last_name' => 'García']);

        $response = $this->actingAs($user)->getJson('/api/employees?search=Carlos');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Carlos', $data[0]['first_name']);
    }

    public function test_employees_search_by_last_name(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Pérez']);
        Employee::factory()->create(['first_name' => 'María', 'last_name' => 'López']);

        $response = $this->actingAs($user)->getJson('/api/employees?search=López');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('López', $data[0]['last_name']);
    }

    public function test_employees_search_by_internal_id(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['internal_id' => 'EMP-001']);
        Employee::factory()->create(['internal_id' => 'EMP-002']);

        $response = $this->actingAs($user)->getJson('/api/employees?search=EMP-001');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('EMP-001', $data[0]['internal_id']);
    }

    public function test_employees_search_returns_empty_when_no_match(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Pérez']);

        $response = $this->actingAs($user)->getJson('/api/employees?search=NoExiste');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(0, $data);
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

    public function test_employees_index_returns_pagination_meta(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->count(5)->create();

        $response = $this->actingAs($user)->getJson('/api/employees');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_employees_index_respects_per_page_parameter(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->count(10)->create();

        $response = $this->actingAs($user)->getJson('/api/employees?per_page=3');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_employees_index_supports_page_navigation(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->count(5)->create();

        $response = $this->actingAs($user)->getJson('/api/employees?per_page=3&page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_employees_index_caps_per_page_at_100(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->getJson('/api/employees?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_employee_show_includes_attendance_summary(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'date_reference' => '2026-01-10',
            'status' => 'present',
            'worked_minutes' => 480,
            'overtime_minutes' => 30,
            'overtime_diurnal_minutes' => 20,
            'overtime_nocturnal_minutes' => 10,
            'late_minutes' => 5,
            'early_departure_minutes' => 0,
        ]);

        AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'date_reference' => '2026-01-11',
            'status' => 'present',
            'worked_minutes' => 500,
            'overtime_minutes' => 60,
            'overtime_diurnal_minutes' => 40,
            'overtime_nocturnal_minutes' => 20,
            'late_minutes' => 0,
            'early_departure_minutes' => 10,
        ]);

        AttendanceDay::factory()->absent()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'date_reference' => '2026-01-12',
        ]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'attendance_summary' => [
                        'total_days_worked',
                        'total_days_absent',
                        'total_days_incomplete',
                        'total_worked_minutes',
                        'total_overtime_minutes',
                        'total_overtime_diurnal_minutes',
                        'total_overtime_nocturnal_minutes',
                        'total_late_minutes',
                        'total_early_departure_minutes',
                    ],
                ],
            ]);

        $summary = $response->json('data.attendance_summary');
        $this->assertEquals(2, $summary['total_days_worked']);
        $this->assertEquals(1, $summary['total_days_absent']);
        $this->assertEquals(0, $summary['total_days_incomplete']);
        $this->assertEquals(980, $summary['total_worked_minutes']);
        $this->assertEquals(90, $summary['total_overtime_minutes']);
        $this->assertEquals(60, $summary['total_overtime_diurnal_minutes']);
        $this->assertEquals(30, $summary['total_overtime_nocturnal_minutes']);
        $this->assertEquals(5, $summary['total_late_minutes']);
        $this->assertEquals(10, $summary['total_early_departure_minutes']);
    }

    public function test_employee_show_summary_with_no_attendance_data(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}");

        $response->assertOk();

        $summary = $response->json('data.attendance_summary');
        $this->assertEquals(0, $summary['total_days_worked']);
        $this->assertEquals(0, $summary['total_days_absent']);
        $this->assertEquals(0, $summary['total_worked_minutes']);
    }

    public function test_employee_index_does_not_include_attendance_summary(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/employees');

        $response->assertOk();
        $firstEmployee = $response->json('data.0');
        $this->assertArrayNotHasKey('attendance_summary', $firstEmployee);
    }
}
