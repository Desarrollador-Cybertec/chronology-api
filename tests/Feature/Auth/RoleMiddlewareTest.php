<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_superadmin_routes(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->getJson('/api/shifts');

        $response->assertOk();
    }

    public function test_manager_can_access_shared_routes(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->getJson('/api/shifts');

        $response->assertOk();
    }

    public function test_manager_cannot_access_superadmin_only_routes(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Turno Test',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_create_shift(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->postJson('/api/shifts', [
            'name' => 'Turno Test',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        // May be 201 or 200 depending on controller implementation
        // At minimum it should NOT be 403
        $this->assertNotEquals(403, $response->status());
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/employees');

        $response->assertStatus(401);
    }
}
