<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceDay;
use App\Models\AttendanceEdit;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private AttendanceDay $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->superadmin()->create();
        $employee = Employee::factory()->create();
        $shift = Shift::factory()->create();

        $this->day = AttendanceDay::factory()->create([
            'employee_id' => $employee->id,
            'shift_id' => $shift->id,
            'date_reference' => '2026-01-15',
        ]);
    }

    public function test_can_show_attendance_day(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->day->id);
    }

    public function test_includes_employee_relation(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['employee' => ['id', 'first_name', 'last_name']],
            ]);
    }

    public function test_includes_shift_relation(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['shift' => ['id', 'name', 'start_time', 'end_time']],
            ]);
    }

    public function test_includes_edit_history(): void
    {
        AttendanceEdit::factory()->create([
            'attendance_day_id' => $this->day->id,
            'edited_by' => $this->user->id,
            'field_changed' => 'status',
            'old_value' => 'absent',
            'new_value' => 'present',
            'reason' => 'Correction',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.edits')
            ->assertJsonStructure([
                'data' => [
                    'edits' => [
                        ['id', 'field_changed', 'old_value', 'new_value', 'reason', 'editor'],
                    ],
                ],
            ]);
    }

    public function test_edit_includes_editor_info(): void
    {
        AttendanceEdit::factory()->create([
            'attendance_day_id' => $this->day->id,
            'edited_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk()
            ->assertJsonPath('data.edits.0.editor.id', $this->user->id)
            ->assertJsonPath('data.edits.0.editor.name', $this->user->name);
    }

    public function test_returns_404_for_nonexistent(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/attendance/99999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_cannot_show(): void
    {
        $response = $this->getJson("/api/attendance/{$this->day->id}");

        $response->assertStatus(401);
    }

    public function test_manager_can_show(): void
    {
        $manager = User::factory()->manager()->create();

        $response = $this->actingAs($manager)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk();
    }

    public function test_resource_date_format(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/attendance/{$this->day->id}");

        $response->assertOk()
            ->assertJsonPath('data.date_reference', '2026-01-15');
    }
}
