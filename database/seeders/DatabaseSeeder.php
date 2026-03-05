<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Users
        User::factory()->superadmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@chronology.test',
            'password' => bcrypt('test123'),
        ]);

        User::factory()->manager()->create([
            'name' => 'Manager User',
            'email' => 'manager@chronology.test',
            'password' => bcrypt('test123'),
        ]);

        // Shifts
        Shift::factory()->create([
            'name' => 'Jornada Completa',
            'start_time' => '07:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);

        // System settings
        $settings = [
            ['key' => 'noise_window_minutes', 'value' => '60', 'group' => 'attendance'],
            ['key' => 'diurnal_start_time', 'value' => '06:00', 'group' => 'attendance'],
            ['key' => 'nocturnal_start_time', 'value' => '20:00', 'group' => 'attendance'],
            ['key' => 'auto_assign_shift', 'value' => 'true', 'group' => 'attendance'],
            ['key' => 'auto_assign_tolerance_minutes', 'value' => '60', 'group' => 'attendance'],
            ['key' => 'lunch_margin_minutes', 'value' => '15', 'group' => 'attendance'],
            ['key' => 'data_retention_months', 'value' => '24', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }
    }
}
