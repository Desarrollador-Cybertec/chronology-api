<?php

namespace Tests\Feature\ScheduleException;

use App\Models\Employee;
use App\Models\EmployeeScheduleException;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleExceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_list_employee_schedule_exceptions(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();
        EmployeeScheduleException::factory()->count(3)->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/schedule-exceptions");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_superadmin_can_list_employee_schedule_exceptions(): void
    {
        $user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        EmployeeScheduleException::factory()->count(2)->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/employees/{$employee->id}/schedule-exceptions");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_can_create_schedule_exception(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions', [
            'employee_id' => $employee->id,
            'date' => '2026-01-17',
            'shift_id' => $shift->id,
            'is_working_day' => true,
            'reason' => 'Inventario',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.date', '2026-01-17')
            ->assertJsonPath('data.is_working_day', true)
            ->assertJsonPath('data.reason', 'Inventario');

        $this->assertDatabaseHas('employee_schedule_exceptions', [
            'employee_id' => $employee->id,
            'is_working_day' => 1,
        ]);
    }

    public function test_manager_can_create_rest_day_exception(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions', [
            'employee_id' => $employee->id,
            'date' => '2026-01-14',
            'is_working_day' => false,
            'reason' => 'Permiso especial',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_working_day', false);
    }

    public function test_upsert_updates_existing_exception(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        EmployeeScheduleException::factory()->create([
            'employee_id' => $employee->id,
            'date' => '2026-01-17',
            'is_working_day' => false,
            'reason' => 'Permiso',
        ]);

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions', [
            'employee_id' => $employee->id,
            'date' => '2026-01-17',
            'shift_id' => $shift->id,
            'is_working_day' => true,
            'reason' => 'Cambio de planes',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_working_day', true)
            ->assertJsonPath('data.reason', 'Cambio de planes');

        $this->assertDatabaseCount('employee_schedule_exceptions', 1);
    }

    public function test_manager_can_show_schedule_exception(): void
    {
        $user = User::factory()->manager()->create();
        $exception = EmployeeScheduleException::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/schedule-exceptions/{$exception->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $exception->id);
    }

    public function test_manager_can_delete_schedule_exception(): void
    {
        $user = User::factory()->manager()->create();
        $exception = EmployeeScheduleException::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/schedule-exceptions/{$exception->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('employee_schedule_exceptions', [
            'id' => $exception->id,
        ]);
    }

    public function test_manager_can_batch_create_exceptions(): void
    {
        $user = User::factory()->manager()->create();
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions/batch', [
            'exceptions' => [
                [
                    'employee_id' => $employee1->id,
                    'date' => '2026-01-17',
                    'shift_id' => $shift->id,
                    'is_working_day' => true,
                    'reason' => 'Inventario',
                ],
                [
                    'employee_id' => $employee2->id,
                    'date' => '2026-01-17',
                    'is_working_day' => false,
                    'reason' => 'Permiso',
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseCount('employee_schedule_exceptions', 2);
    }

    public function test_unauthenticated_user_cannot_access_schedule_exceptions(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->getJson("/api/employees/{$employee->id}/schedule-exceptions");

        $response->assertUnauthorized();
    }

    public function test_validation_rejects_invalid_employee_id(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions', [
            'employee_id' => 9999,
            'date' => '2026-01-17',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('employee_id');
    }

    public function test_validation_rejects_missing_date(): void
    {
        $user = User::factory()->manager()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions', [
            'employee_id' => $employee->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_batch_validation_rejects_empty_array(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->postJson('/api/schedule-exceptions/batch', [
            'exceptions' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('exceptions');
    }
}
