<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user', 'message'])
            ->assertJsonMissing(['token'])
            ->assertJsonPath('user.role', 'manager');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_registration_ignores_role_field(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Sneaky User',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'superadmin',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'manager');

        $this->assertDatabaseHas('users', [
            'email' => 'sneaky@example.com',
            'role' => 'manager',
        ]);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Short Pass',
            'email' => 'short@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas.');
    }

    public function test_login_fails_with_non_existent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Sesión cerrada correctamente.');
    }

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_login_blocked_when_user_has_active_session(): void
    {
        $user = User::factory()->create([
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);
        $response->assertOk()->assertJsonStructure(['token']);

        $response = $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => 'password123',
        ]);
        $response->assertStatus(409)
            ->assertJsonPath('message', 'Este usuario ya tiene una sesión activa. Debe cerrar la sesión existente antes de iniciar una nueva.');
    }

    public function test_login_allowed_after_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'relogin@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'relogin@example.com',
            'password' => 'password123',
        ]);
        $response->assertOk();
        $token = $response->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $response = $this->postJson('/api/login', [
            'email' => 'relogin@example.com',
            'password' => 'password123',
        ]);
        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_different_users_can_login_simultaneously(): void
    {
        User::factory()->create([
            'email' => 'user1@example.com',
            'password' => 'password123',
        ]);
        User::factory()->create([
            'email' => 'user2@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'user1@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'user2@example.com',
            'password' => 'password123',
        ])->assertOk();
    }
}
