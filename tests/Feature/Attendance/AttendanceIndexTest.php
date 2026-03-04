<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->superadmin()->create();
        $this->employee = Employee::factory()->create();
        $this->shift = Shift::factory()->create();
    }

    public function test_can_list_attendance_days(): void
    {
        AttendanceDay::factory()->count(3)
            ->sequence(
                ['date_reference' => '2026-01-10'],
                ['date_reference' => '2026-01-11'],
                ['date_reference' => '2026-01-12'],
            )
            ->create([
                'employee_id' => $this->employee->id,
                'shift_id' => $this->shift->id,
            ]);

        $response = $this->actingAs($this->user)->getJson('/api/attendance');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_returns_paginated_response(): void
    {
        AttendanceDay::factory()->count(5)
            ->sequence(
                ['date_reference' => '2026-01-10'],
                ['date_reference' => '2026-01-11'],
                ['date_reference' => '2026-01-12'],
                ['date_reference' => '2026-01-13'],
                ['date_reference' => '2026-01-14'],
            )
            ->create([
                'employee_id' => $this->employee->id,
                'shift_id' => $this->shift->id,
            ]);

        $response = $this->actingAs($this->user)->getJson('/api/attendance?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_includes_employee_and_shift_relations(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'employee_id', 'employee' => ['id', 'first_name'], 'shift' => ['id', 'name']],
                ],
            ]);
    }

    public function test_filters_by_employee_id(): void
    {
        $emp2 = Employee::factory()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance?employee_id={$this->employee->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_id', $this->employee->id);
    }

    public function test_filters_by_date(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-16',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?date=2026-01-15');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filters_by_date_range(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-10',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-20',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?date_from=2026-01-12&date_to=2026-01-18');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filters_by_status(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
            'status' => 'present',
        ]);
        AttendanceDay::factory()->absent()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-16',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?status=absent');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'absent');
    }

    public function test_filters_by_has_overtime(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
            'overtime_minutes' => 60,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-16',
            'overtime_minutes' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?has_overtime=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_filters_by_has_late(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
            'late_minutes' => 15,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-16',
            'late_minutes' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?has_late=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_by_employee_endpoint(): void
    {
        $emp2 = Employee::factory()->create();

        AttendanceDay::factory()->count(2)
            ->sequence(
                ['date_reference' => '2026-01-10'],
                ['date_reference' => '2026-01-11'],
            )
            ->create([
                'employee_id' => $this->employee->id,
                'shift_id' => $this->shift->id,
            ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/employee/{$this->employee->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_by_employee_with_date_filter(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-02-15',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/employee/{$this->employee->id}?date_from=2026-02-01");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_by_date_endpoint(): void
    {
        $emp2 = Employee::factory()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-16',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance/day/2026-01-15');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_by_date_with_status_filter(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
            'status' => 'present',
        ]);
        $emp2 = Employee::factory()->create();
        AttendanceDay::factory()->absent()->create([
            'employee_id' => $emp2->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance/day/2026-01-15?status=present');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_unauthenticated_cannot_access(): void
    {
        $response = $this->getJson('/api/attendance');

        $response->assertStatus(401);
    }

    public function test_manager_can_access_attendance(): void
    {
        $manager = User::factory()->manager()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
        ]);

        $response = $this->actingAs($manager)->getJson('/api/attendance');

        $response->assertOk();
    }

    public function test_resource_has_correct_structure(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'shift_id' => $this->shift->id,
            'date_reference' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'employee_id',
                        'employee',
                        'date_reference',
                        'shift_id',
                        'shift',
                        'first_check_in',
                        'last_check_out',
                        'worked_minutes',
                        'overtime_minutes',
                        'overtime_diurnal_minutes',
                        'overtime_nocturnal_minutes',
                        'late_minutes',
                        'early_departure_minutes',
                        'status',
                        'is_manually_edited',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }
}
