<?php

namespace Tests\Feature\SystemSetting;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_list_settings(): void
    {
        $user = User::factory()->superadmin()->create();
        SystemSetting::factory()->count(3)->create();

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'key', 'value', 'group'],
                ],
            ]);
    }

    public function test_manager_cannot_list_settings(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->getJson('/api/settings');

        $response->assertStatus(403);
    }

    public function test_superadmin_can_update_settings(): void
    {
        $user = User::factory()->superadmin()->create();
        SystemSetting::updateOrCreate(
            ['key' => 'noise_window_minutes'],
            ['value' => '60', 'group' => 'engine'],
        );

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'settings' => [
                ['key' => 'noise_window_minutes', 'value' => '90'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Configuración actualizada correctamente.');

        $this->assertDatabaseHas('system_settings', [
            'key' => 'noise_window_minutes',
            'value' => '90',
        ]);
    }

    public function test_superadmin_can_upsert_new_setting(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'settings' => [
                ['key' => 'new_setting', 'value' => 'test_value'],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('system_settings', [
            'key' => 'new_setting',
            'value' => 'test_value',
        ]);
    }

    public function test_superadmin_can_bulk_update_settings(): void
    {
        $user = User::factory()->superadmin()->create();
        SystemSetting::create(['key' => 'setting_a', 'value' => 'old_a', 'group' => 'general']);
        SystemSetting::create(['key' => 'setting_b', 'value' => 'old_b', 'group' => 'general']);

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'settings' => [
                ['key' => 'setting_a', 'value' => 'new_a'],
                ['key' => 'setting_b', 'value' => 'new_b'],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('system_settings', ['key' => 'setting_a', 'value' => 'new_a']);
        $this->assertDatabaseHas('system_settings', ['key' => 'setting_b', 'value' => 'new_b']);
    }

    public function test_manager_cannot_update_settings(): void
    {
        $user = User::factory()->manager()->create();

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'settings' => [
                ['key' => 'test', 'value' => 'hack'],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_update_fails_without_settings_array(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->putJson('/api/settings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('settings');
    }

    public function test_update_fails_with_missing_key(): void
    {
        $user = User::factory()->superadmin()->create();

        $response = $this->actingAs($user)->putJson('/api/settings', [
            'settings' => [
                ['value' => 'no_key'],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('settings.0.key');
    }

    public function test_unauthenticated_cannot_access_settings(): void
    {
        $response = $this->getJson('/api/settings');

        $response->assertStatus(401);
    }
}
