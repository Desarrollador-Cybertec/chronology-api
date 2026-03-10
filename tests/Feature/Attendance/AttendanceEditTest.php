<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDay;
use App\Models\AttendanceEdit;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceEditTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private AttendanceDay $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();

        $this->day = AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'date_reference' => '2026-01-15',
            'status' => 'present',
            'worked_minutes' => 540,
            'late_minutes' => 0,
            'overtime_minutes' => 0,
        ]);
    }

    public function test_superadmin_can_edit_attendance(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
                'reason' => 'Employee was not actually present',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'absent');
    }

    public function test_edit_creates_audit_record(): void
    {
        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
                'reason' => 'Correction',
            ]);

        $this->assertDatabaseCount('attendance_edits', 1);
        $this->assertDatabaseHas('attendance_edits', [
            'attendance_day_id' => $this->day->id,
            'edited_by' => $this->superadmin->id,
            'field_changed' => 'status',
            'old_value' => 'present',
            'new_value' => 'absent',
            'reason' => 'Correction',
        ]);
    }

    public function test_edit_multiple_fields_creates_multiple_records(): void
    {
        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
                'worked_minutes' => 0,
                'reason' => 'Full correction',
            ]);

        $this->assertDatabaseCount('attendance_edits', 2);
    }

    public function test_marks_day_as_manually_edited(): void
    {
        $this->assertFalse($this->day->is_manually_edited);

        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'late_minutes' => 15,
                'reason' => 'Late arrival confirmed',
            ]);

        $this->day->refresh();
        $this->assertTrue($this->day->is_manually_edited);
    }

    public function test_does_not_create_edit_if_value_unchanged(): void
    {
        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'present',
                'reason' => 'No change',
            ]);

        $this->assertDatabaseCount('attendance_edits', 0);
    }

    public function test_returns_edits_created_count(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
                'worked_minutes' => 0,
                'reason' => 'Correction',
            ]);

        $response->assertOk()
            ->assertJsonPath('edits_created', 2);
    }

    public function test_reason_is_required(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_validates_status_enum(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'invalid_status',
                'reason' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_validates_minutes_are_non_negative(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'worked_minutes' => -10,
                'reason' => 'Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('worked_minutes');
    }

    public function test_manager_cannot_edit_attendance(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
                'reason' => 'Test',
            ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_edit(): void
    {
        $response = $this->putJson("/api/attendance/{$this->day->id}", [
            'status' => 'absent',
            'reason' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_can_edit_first_check_in(): void
    {
        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'first_check_in' => '2026-01-15 07:55:00',
                'reason' => 'Clock was wrong',
            ]);

        $this->day->refresh();
        $this->assertEquals('2026-01-15 07:55:00', $this->day->first_check_in->format('Y-m-d H:i:s'));

        $this->assertDatabaseHas('attendance_edits', [
            'field_changed' => 'first_check_in',
            'new_value' => '2026-01-15 07:55:00',
        ]);
    }

    public function test_can_edit_overtime_fields(): void
    {
        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'overtime_minutes' => 120,
                'overtime_diurnal_minutes' => 80,
                'overtime_nocturnal_minutes' => 40,
                'reason' => 'Overtime approved',
            ]);

        $this->day->refresh();
        $this->assertEquals(120, $this->day->overtime_minutes);
        $this->assertEquals(80, $this->day->overtime_diurnal_minutes);
        $this->assertEquals(40, $this->day->overtime_nocturnal_minutes);

        $this->assertDatabaseCount('attendance_edits', 3);
    }

    public function test_returns_full_resource_with_edit_history(): void
    {
        // Create a pre-existing edit
        AttendanceEdit::factory()->create([
            'attendance_day_id' => $this->day->id,
            'edited_by' => $this->superadmin->id,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'status' => 'absent',
                'reason' => 'Second edit',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'employee', 'edits',
                ],
                'edits_created',
            ])
            ->assertJsonCount(2, 'data.edits');
    }

    public function test_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->putJson('/api/attendance/99999', [
                'status' => 'absent',
                'reason' => 'Test',
            ]);

        $response->assertStatus(404);
    }

    public function test_can_edit_early_departure(): void
    {
        $this->actingAs($this->superadmin)
            ->putJson("/api/attendance/{$this->day->id}", [
                'early_departure_minutes' => 30,
                'reason' => 'Left early for appointment',
            ]);

        $this->day->refresh();
        $this->assertEquals(30, $this->day->early_departure_minutes);
    }
}
