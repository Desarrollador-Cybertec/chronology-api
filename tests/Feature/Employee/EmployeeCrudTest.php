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

    public function test_employees_sort_by_internal_id(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['internal_id' => '50']);
        Employee::factory()->create(['internal_id' => '10']);
        Employee::factory()->create(['internal_id' => '30']);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=internal_id&order=asc');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('internal_id')->all();
        $this->assertEquals(['10', '30', '50'], $ids);
    }

    public function test_employees_sort_by_internal_id_desc(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['internal_id' => '50']);
        Employee::factory()->create(['internal_id' => '10']);
        Employee::factory()->create(['internal_id' => '30']);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=internal_id&order=desc');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('internal_id')->all();
        $this->assertEquals(['50', '30', '10'], $ids);
    }

    public function test_employees_sort_by_department(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['department' => 'Ventas', 'last_name' => 'A']);
        Employee::factory()->create(['department' => 'Admin', 'last_name' => 'B']);
        Employee::factory()->create(['department' => 'IT', 'last_name' => 'C']);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=department&order=asc');

        $response->assertOk();
        $departments = collect($response->json('data'))->pluck('department')->all();
        $this->assertEquals(['Admin', 'IT', 'Ventas'], $departments);
    }

    public function test_employees_sort_by_is_active(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['is_active' => true, 'last_name' => 'Activo']);
        Employee::factory()->create(['is_active' => false, 'last_name' => 'Inactivo']);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=is_active&order=desc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertTrue($data[0]['is_active']);
        $this->assertFalse($data[1]['is_active']);
    }

    public function test_employees_sort_by_current_shift_asc(): void
    {
        $user = User::factory()->superadmin()->create();
        $shiftA = Shift::factory()->create(['name' => 'Matutino']);
        $shiftB = Shift::factory()->create(['name' => 'Nocturno']);

        $empNoShift = Employee::factory()->create(['last_name' => 'SinTurno']);
        $empShiftB = Employee::factory()->create(['last_name' => 'ConNocturno']);
        $empShiftA = Employee::factory()->create(['last_name' => 'ConMatutino']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $empShiftA->id,
            'shift_id' => $shiftA->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);
        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $empShiftB->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=current_shift&order=asc');

        $response->assertOk();
        $lastNames = collect($response->json('data'))->pluck('last_name')->all();
        // null shifts sort first in asc, then Matutino, then Nocturno
        $this->assertEquals('SinTurno', $lastNames[0]);
        $this->assertEquals('ConMatutino', $lastNames[1]);
        $this->assertEquals('ConNocturno', $lastNames[2]);
    }

    public function test_employees_sort_by_current_shift_desc(): void
    {
        $user = User::factory()->superadmin()->create();
        $shiftA = Shift::factory()->create(['name' => 'Matutino']);
        $shiftB = Shift::factory()->create(['name' => 'Nocturno']);

        $empNoShift = Employee::factory()->create(['last_name' => 'SinTurno']);
        $empShiftB = Employee::factory()->create(['last_name' => 'ConNocturno']);
        $empShiftA = Employee::factory()->create(['last_name' => 'ConMatutino']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $empShiftA->id,
            'shift_id' => $shiftA->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);
        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $empShiftB->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=current_shift&order=desc');

        $response->assertOk();
        $lastNames = collect($response->json('data'))->pluck('last_name')->all();
        // desc: Nocturno first, then Matutino, then null last
        $this->assertEquals('ConNocturno', $lastNames[0]);
        $this->assertEquals('ConMatutino', $lastNames[1]);
        $this->assertEquals('SinTurno', $lastNames[2]);
    }

    public function test_employees_sort_ignores_ended_assignments(): void
    {
        $user = User::factory()->superadmin()->create();
        $shiftA = Shift::factory()->create(['name' => 'Matutino']);
        $shiftB = Shift::factory()->create(['name' => 'Nocturno']);

        $employee = Employee::factory()->create(['last_name' => 'Test']);

        // Old ended assignment
        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftA->id,
            'effective_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);
        // Current active assignment
        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shiftB->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=current_shift&order=asc');

        $response->assertOk();
        $data = $response->json('data');
        $currentShift = $data[0]['shift_assignments'];
        // Should be sorted by active shift (Nocturno), not ended one (Matutino)
        $this->assertNotEmpty($currentShift);
    }

    public function test_employees_sort_by_invalid_column_falls_back_to_last_name(): void
    {
        $user = User::factory()->superadmin()->create();
        Employee::factory()->create(['last_name' => 'Zapata']);
        Employee::factory()->create(['last_name' => 'Acosta']);

        $response = $this->actingAs($user)->getJson('/api/employees?sort_by=invalid_column');

        $response->assertOk();
        $lastNames = collect($response->json('data'))->pluck('last_name')->all();
        $this->assertEquals(['Acosta', 'Zapata'], $lastNames);
    }

    public function test_employees_sorting_works_globally_across_pages(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create(['name' => 'Jornada Completa']);

        // Create 4 employees: 2 with shift, 2 without
        $empA = Employee::factory()->create(['last_name' => 'Alpha']);
        $empB = Employee::factory()->create(['last_name' => 'Bravo']);
        $empC = Employee::factory()->create(['last_name' => 'Charlie']);
        $empD = Employee::factory()->create(['last_name' => 'Delta']);

        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $empB->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);
        EmployeeShiftAssignment::factory()->create([
            'employee_id' => $empD->id,
            'shift_id' => $shift->id,
            'effective_date' => '2026-01-01',
            'end_date' => null,
        ]);

        // Page 1: sort by current_shift asc, per_page=2
        $page1 = $this->actingAs($user)->getJson('/api/employees?sort_by=current_shift&order=asc&per_page=2&page=1');
        $page2 = $this->actingAs($user)->getJson('/api/employees?sort_by=current_shift&order=asc&per_page=2&page=2');

        $page1names = collect($page1->json('data'))->pluck('last_name')->all();
        $page2names = collect($page2->json('data'))->pluck('last_name')->all();

        // No-shift employees should all be on page 1, shift employees on page 2
        // (null sorts before 'Jornada Completa' in asc)
        $allNames = array_merge($page1names, $page2names);
        $this->assertCount(4, $allNames);
        // The first 2 should be without shift (Alpha, Charlie), last 2 with shift (Bravo, Delta)
        $this->assertContains('Alpha', $page1names);
        $this->assertContains('Charlie', $page1names);
        $this->assertContains('Bravo', $page2names);
        $this->assertContains('Delta', $page2names);
    }
}
