<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->superadmin()->create();
        $this->employee = Employee::factory()->create();
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

    public function test_includes_employee_relation(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/attendance');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'employee_id', 'employee' => ['id', 'first_name']],
                ],
            ]);
    }

    public function test_filters_by_employee_id(): void
    {
        $emp2 = Employee::factory()->create();

        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
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
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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
            'date_reference' => '2026-01-10',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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
            'date_reference' => '2026-01-15',
            'status' => 'present',
        ]);
        AttendanceDay::factory()->absent()->create([
            'employee_id' => $this->employee->id,
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
            'date_reference' => '2026-01-15',
            'overtime_minutes' => 60,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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
            'date_reference' => '2026-01-15',
            'late_minutes' => 15,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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
            ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
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
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $emp2->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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
            'date_reference' => '2026-01-15',
            'status' => 'present',
        ]);
        $emp2 = Employee::factory()->create();
        AttendanceDay::factory()->absent()->create([
            'employee_id' => $emp2->id,
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
        ]);

        $response = $this->actingAs($manager)->getJson('/api/attendance');

        $response->assertOk();
    }

    public function test_resource_has_correct_structure(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
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

    public function test_sort_by_worked_minutes_asc(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'worked_minutes' => 540,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-16',
            'worked_minutes' => 300,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-17',
            'worked_minutes' => 480,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=worked_minutes&order=asc');

        $response->assertOk();
        $minutes = collect($response->json('data'))->pluck('worked_minutes')->all();
        $this->assertEquals([300, 480, 540], $minutes);
    }

    public function test_sort_by_overtime_minutes_desc(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'overtime_minutes' => 30,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-16',
            'overtime_minutes' => 120,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=overtime_minutes&order=desc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(120, $data[0]['overtime_minutes']);
        $this->assertEquals(30, $data[1]['overtime_minutes']);
    }

    public function test_sort_by_status(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'status' => 'present',
        ]);
        AttendanceDay::factory()->absent()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-16',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=status&order=asc');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->all();
        $this->assertEquals(['absent', 'present'], $statuses);
    }

    public function test_sort_by_employee_name(): void
    {
        $empA = Employee::factory()->create(['last_name' => 'Acosta']);
        $empZ = Employee::factory()->create(['last_name' => 'Zapata']);

        AttendanceDay::factory()->create([
            'employee_id' => $empZ->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $empA->id,
            'date_reference' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=employee&order=asc');

        $response->assertOk();
        $lastNames = collect($response->json('data'))->pluck('employee.last_name')->all();
        $this->assertEquals(['Acosta', 'Zapata'], $lastNames);
    }

    public function test_sort_by_date_reference_asc(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-20',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-10',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=date_reference&order=asc');

        $response->assertOk();
        $dates = collect($response->json('data'))->pluck('date_reference')->all();
        $this->assertEquals('2026-01-10', $dates[0]);
        $this->assertEquals('2026-01-20', $dates[1]);
    }

    public function test_default_sort_is_date_reference_desc(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-10',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-20',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance');

        $response->assertOk();
        $dates = collect($response->json('data'))->pluck('date_reference')->all();
        $this->assertEquals('2026-01-20', $dates[0]);
        $this->assertEquals('2026-01-10', $dates[1]);
    }

    public function test_invalid_sort_column_falls_back_to_date_reference(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-10',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-20',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=hacked_column');

        $response->assertOk();
        $dates = collect($response->json('data'))->pluck('date_reference')->all();
        $this->assertEquals('2026-01-20', $dates[0]);
        $this->assertEquals('2026-01-10', $dates[1]);
    }

    public function test_sorting_works_across_pages(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'late_minutes' => 5,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-16',
            'late_minutes' => 30,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-17',
            'late_minutes' => 15,
        ]);

        $page1 = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=late_minutes&order=asc&per_page=2&page=1');
        $page2 = $this->actingAs($this->user)
            ->getJson('/api/attendance?sort_by=late_minutes&order=asc&per_page=2&page=2');

        $p1 = collect($page1->json('data'))->pluck('late_minutes')->all();
        $p2 = collect($page2->json('data'))->pluck('late_minutes')->all();

        $this->assertEquals([5, 15], $p1);
        $this->assertEquals([30], $p2);
    }

    public function test_by_employee_endpoint_supports_sorting(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-15',
            'worked_minutes' => 540,
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-01-16',
            'worked_minutes' => 300,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/employee/{$this->employee->id}?sort_by=worked_minutes&order=asc");

        $response->assertOk();
        $minutes = collect($response->json('data'))->pluck('worked_minutes')->all();
        $this->assertEquals([300, 540], $minutes);
    }

    public function test_by_date_endpoint_supports_sorting(): void
    {
        $empA = Employee::factory()->create(['last_name' => 'Acosta']);
        $empZ = Employee::factory()->create(['last_name' => 'Zapata']);

        AttendanceDay::factory()->create([
            'employee_id' => $empZ->id,
            'date_reference' => '2026-01-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $empA->id,
            'date_reference' => '2026-01-15',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance/day/2026-01-15?sort_by=employee&order=asc');

        $response->assertOk();
        $lastNames = collect($response->json('data'))->pluck('employee.last_name')->all();
        $this->assertEquals(['Acosta', 'Zapata'], $lastNames);
    }
}
