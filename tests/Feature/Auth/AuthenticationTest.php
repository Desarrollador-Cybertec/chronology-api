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
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
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
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
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
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Short Pass',
            'email' => 'short@example.com',
            'password' => 'Ab1234567',
            'password_confirmation' => 'Ab1234567',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'Password1234',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'Password1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => 'Password1234',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'WrongPassword1',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Credenciales inválidas.');
    }

    public function test_login_fails_with_non_existent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'Password1234',
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
            'password' => 'Password1234',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => 'Password1234',
        ]);
        $response->assertOk()->assertJsonStructure(['token']);

        $response = $this->postJson('/api/login', [
            'email' => 'active@example.com',
            'password' => 'Password1234',
        ]);
        $response->assertStatus(409)
            ->assertJsonPath('message', 'Este usuario ya tiene una sesión activa. Debe cerrar la sesión existente antes de iniciar una nueva.');
    }

    public function test_login_allowed_after_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'relogin@example.com',
            'password' => 'Password1234',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'relogin@example.com',
            'password' => 'Password1234',
        ]);
        $response->assertOk();
        $token = $response->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk();

        $response = $this->postJson('/api/login', [
            'email' => 'relogin@example.com',
            'password' => 'Password1234',
        ]);
        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_different_users_can_login_simultaneously(): void
    {
        User::factory()->create([
            'email' => 'user1@example.com',
            'password' => 'Password1234',
        ]);
        User::factory()->create([
            'email' => 'user2@example.com',
            'password' => 'Password1234',
        ]);

        $this->postJson('/api/login', [
            'email' => 'user1@example.com',
            'password' => 'Password1234',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => 'user2@example.com',
            'password' => 'Password1234',
        ])->assertOk();
    }

    public function test_registration_fails_with_weak_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Weak Pass',
            'email' => 'weak@example.com',
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_fails_without_mixed_case(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'No Upper',
            'email' => 'noupper@example.com',
            'password' => 'password1234',
            'password_confirmation' => 'password1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_fails_without_numbers(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'No Numbers',
            'email' => 'nonums@example.com',
            'password' => 'PasswordOnly',
            'password_confirmation' => 'PasswordOnly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'rate@example.com',
            'password' => 'Password1234',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'rate@example.com',
                'password' => 'WrongPassword1',
            ]);
        }

        $response = $this->postJson('/api/login', [
            'email' => 'rate@example.com',
            'password' => 'Password1234',
        ]);

        $response->assertStatus(429);
    }
}
