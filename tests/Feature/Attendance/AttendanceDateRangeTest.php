<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDateRangeTest extends TestCase
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

    public function test_returns_null_dates_when_no_data(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/attendance/date-range');

        $response->assertOk()->assertJson([
            'data' => [
                'min_date' => null,
                'max_date' => null,
            ],
        ]);
    }

    public function test_returns_min_and_max_processed_dates(): void
    {
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-04-01',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-04-15',
        ]);
        AttendanceDay::factory()->create([
            'employee_id' => $this->employee->id,
            'date_reference' => '2026-04-30',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/attendance/date-range');

        $response->assertOk()->assertJson([
            'data' => [
                'min_date' => '2026-04-01',
                'max_date' => '2026-04-30',
            ],
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/attendance/date-range')->assertUnauthorized();
    }

    public function test_manager_can_access_date_range(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);

        $this->actingAs($manager)->getJson('/api/attendance/date-range')->assertOk();
    }
}
