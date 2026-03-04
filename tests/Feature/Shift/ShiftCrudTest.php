<?php

namespace Tests\Feature\Shift;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_shifts(): void
    {
        $user = User::factory()->superadmin()->create();
        Shift::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/shifts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'start_time', 'end_time',
                        'crosses_midnight', 'lunch_required', 'lunch_duration_minutes',
                        'tolerance_minutes', 'overtime_enabled', 'overtime_min_block_minutes',
                        'max_daily_overtime_minutes', 'is_active',
                    ],
                ],
            ]);
    }

    public function test_manager_can_list_shifts(): void
    {
        $user = User::factory()->manager()->create();
        Shift::factory()->count(2)->create();

        $response = $this->actingAs($user)->getJson('/api/shifts');

        $response->assertOk();
    }

    public function test_superadmin_can_create_shift(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Turno Mañana',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'tolerance_minutes' => 15,
            'lunch_required' => true,
            'lunch_duration_minutes' => 60,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Turno Mañana')
            ->assertJsonPath('data.tolerance_minutes', 15)
            ->assertJsonPath('data.lunch_required', true)
            ->assertJsonPath('data.lunch_duration_minutes', 60);

        $this->assertDatabaseHas('shifts', ['name' => 'Turno Mañana']);
    }

    public function test_superadmin_can_create_night_shift(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Nocturno',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
            'overtime_enabled' => true,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 240,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.crosses_midnight', true)
            ->assertJsonPath('data.overtime_enabled', true);
    }

    public function test_manager_cannot_create_shift(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Turno Test',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_show_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->getJson("/api/shifts/{$shift->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $shift->id);
    }

    public function test_superadmin_can_update_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/shifts/{$shift->id}", [
            'name' => 'Turno Actualizado',
            'tolerance_minutes' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Turno Actualizado')
            ->assertJsonPath('data.tolerance_minutes', 20);
    }

    public function test_manager_cannot_update_shift(): void
    {
        $user = User::factory()->manager()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->putJson("/api/shifts/{$shift->id}", [
            'name' => 'Hack',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_delete_shift(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/shifts/{$shift->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Turno eliminado correctamente.');

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_manager_cannot_delete_shift(): void
    {
        $user = User::factory()->manager()->create();
        $shift = Shift::factory()->create();

        $response = $this->actingAs($user)->deleteJson("/api/shifts/{$shift->id}");

        $response->assertStatus(403);
    }

    public function test_create_shift_fails_with_missing_name(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_create_shift_fails_with_invalid_time_format(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Bad Time',
            'start_time' => '8am',
            'end_time' => '5pm',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_time', 'end_time']);
    }

    public function test_create_shift_fails_with_invalid_tolerance(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Bad Tolerance',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('tolerance_minutes');
    }
}
