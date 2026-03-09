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
                        'crosses_midnight',
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
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Turno Mañana')
            ->assertJsonPath('data.tolerance_minutes', 15);

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

    public function test_shifts_index_returns_pagination_meta(): void
    {
        $user = User::factory()->superadmin()->create();
        Shift::factory()->count(5)->create();

        $expected = Shift::count();

        $response = $this->actingAs($user)->getJson('/api/shifts');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            ->assertJsonPath('meta.total', $expected)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_shifts_index_respects_per_page_parameter(): void
    {
        $user = User::factory()->superadmin()->create();
        Shift::factory()->count(10)->create();

        $response = $this->actingAs($user)->getJson('/api/shifts?per_page=4');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 4)
            ->assertJsonCount(4, 'data');
    }

    public function test_shifts_index_supports_page_navigation(): void
    {
        $user = User::factory()->superadmin()->create();
        Shift::factory()->count(5)->create();

        $total = Shift::count();
        $perPage = 3;
        $expectedOnPage2 = $total - $perPage;

        $response = $this->actingAs($user)->getJson('/api/shifts?per_page=3&page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonCount($expectedOnPage2, 'data');
    }

    public function test_shifts_index_caps_per_page_at_100(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->getJson('/api/shifts?per_page=999');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_superadmin_can_create_shift_with_breaks(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Turno con Descansos',
            'start_time' => '07:00',
            'end_time' => '16:00',
            'breaks' => [
                ['type' => 'morning_snack', 'start_time' => '09:30', 'end_time' => '09:45', 'duration_minutes' => 15, 'position' => 0],
                ['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'position' => 1],
                ['type' => 'afternoon_snack', 'start_time' => '15:00', 'end_time' => '15:15', 'duration_minutes' => 15, 'position' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Turno con Descansos')
            ->assertJsonCount(3, 'data.breaks');

        $breaks = $response->json('data.breaks');
        $this->assertEquals('morning_snack', $breaks[0]['type']);
        $this->assertEquals(15, $breaks[0]['duration_minutes']);
        $this->assertEquals('lunch', $breaks[1]['type']);
        $this->assertEquals(30, $breaks[1]['duration_minutes']);
        $this->assertEquals('afternoon_snack', $breaks[2]['type']);
        $this->assertEquals(15, $breaks[2]['duration_minutes']);

        $createdShiftId = $response->json('data.id');
        $this->assertSame(3, \App\Models\ShiftBreak::where('shift_id', $createdShiftId)->count());
    }

    public function test_superadmin_can_create_shift_without_breaks(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Turno Sin Descansos',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Turno Sin Descansos')
            ->assertJsonCount(0, 'data.breaks');
    }

    public function test_shift_show_includes_breaks(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();
        \App\Models\ShiftBreak::factory()->morningSnack()->create(['shift_id' => $shift->id]);
        \App\Models\ShiftBreak::factory()->lunch()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($user)->getJson("/api/shifts/{$shift->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.breaks')
            ->assertJsonStructure([
                'data' => [
                    'breaks' => [
                        '*' => ['id', 'type', 'start_time', 'end_time', 'duration_minutes', 'position'],
                    ],
                ],
            ]);
    }

    public function test_shift_index_includes_breaks(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create(['name' => 'ZZZZ Último']);
        \App\Models\ShiftBreak::factory()->lunch()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($user)->getJson('/api/shifts');

        $response->assertOk();
        $lastShift = collect($response->json('data'))->firstWhere('id', $shift->id);
        $this->assertNotNull($lastShift);
        $this->assertCount(1, $lastShift['breaks']);
    }

    public function test_superadmin_can_update_shift_breaks(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();
        \App\Models\ShiftBreak::factory()->morningSnack()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($user)->putJson("/api/shifts/{$shift->id}", [
            'name' => $shift->name,
            'breaks' => [
                ['type' => 'lunch', 'start_time' => '12:00', 'end_time' => '13:00', 'duration_minutes' => 60, 'position' => 0],
                ['type' => 'afternoon_snack', 'start_time' => '15:00', 'end_time' => '15:15', 'duration_minutes' => 15, 'position' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.breaks');

        $this->assertSame(2, \App\Models\ShiftBreak::where('shift_id', $shift->id)->count());
        $this->assertDatabaseHas('shift_breaks', ['shift_id' => $shift->id, 'type' => 'lunch']);
        $this->assertDatabaseHas('shift_breaks', ['shift_id' => $shift->id, 'type' => 'afternoon_snack']);
    }

    public function test_update_shift_without_breaks_key_preserves_existing_breaks(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();
        \App\Models\ShiftBreak::factory()->lunch()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($user)->putJson("/api/shifts/{$shift->id}", [
            'name' => 'Nombre Actualizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Nombre Actualizado');

        $this->assertSame(1, \App\Models\ShiftBreak::where('shift_id', $shift->id)->count());
    }

    public function test_create_shift_fails_with_invalid_break_data(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Bad Breaks',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'breaks' => [
                ['type' => '', 'start_time' => 'invalid', 'end_time' => '13:00', 'duration_minutes' => 0],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_deleting_shift_cascades_to_breaks(): void
    {
        $user = User::factory()->superadmin()->create();
        $shift = Shift::factory()->create();
        \App\Models\ShiftBreak::factory()->count(3)->create(['shift_id' => $shift->id]);

        $this->assertSame(3, \App\Models\ShiftBreak::where('shift_id', $shift->id)->count());

        $this->actingAs($user)->deleteJson("/api/shifts/{$shift->id}");

        $this->assertDatabaseMissing('shift_breaks', ['shift_id' => $shift->id]);
    }
}
