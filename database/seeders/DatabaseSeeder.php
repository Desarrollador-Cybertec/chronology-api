<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ── Users ────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@chronology.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('Test123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'insumma.admin@chronology.test'],
            [
                'name' => 'Insumma Admin',
                'password' => Hash::make('Test123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@chronology.test'],
            [
                'name' => 'Manager User',
                'password' => Hash::make('Test123'),
                'role' => 'manager',
                'email_verified_at' => now(),
            ]
        );

        // ── Shifts ───────────────────────────────────────────────
        $horario1 = Shift::updateOrCreate(
            ['name' => 'Horario 1'],
            [
                'start_time' => '07:00',
                'end_time' => '17:00',
                'crosses_midnight' => false,
                'tolerance_minutes' => 10,
                'overtime_enabled' => true,
                'overtime_min_block_minutes' => 30,
                'max_daily_overtime_minutes' => 120,
                'is_active' => true,
            ]
        );

        $horario1->breaks()->delete();
        $horario1->breaks()->createMany([
            ['type' => 'morning_snack', 'start_time' => '09:00', 'end_time' => '09:15', 'duration_minutes' => 15, 'position' => 1],
            ['type' => 'lunch', 'start_time' => '13:00', 'end_time' => '13:30', 'duration_minutes' => 30, 'position' => 2],
            ['type' => 'afternoon_snack', 'start_time' => '16:00', 'end_time' => '16:15', 'duration_minutes' => 15, 'position' => 3],
        ]);

        $turno2 = Shift::updateOrCreate(
            ['name' => 'Turno 2'],
            [
                'start_time' => '08:00',
                'end_time' => '17:00',
                'crosses_midnight' => false,
                'tolerance_minutes' => 10,
                'overtime_enabled' => true,
                'overtime_min_block_minutes' => 30,
                'max_daily_overtime_minutes' => 120,
                'is_active' => true,
            ]
        );

        $turno2->breaks()->delete();
        $turno2->breaks()->createMany([
            ['type' => 'morning_snack', 'start_time' => '09:00', 'end_time' => '09:15', 'duration_minutes' => 15, 'position' => 1],
            ['type' => 'lunch', 'start_time' => '14:00', 'end_time' => '14:30', 'duration_minutes' => 30, 'position' => 2],
            ['type' => 'afternoon_snack', 'start_time' => '16:00', 'end_time' => '16:15', 'duration_minutes' => 15, 'position' => 3],
        ]);

        // ── System settings ──────────────────────────────────────
        $settings = [
            ['key' => 'noise_window_minutes',          'value' => '30',   'group' => 'attendance'],
            ['key' => 'diurnal_start_time',             'value' => '06:00', 'group' => 'attendance'],
            ['key' => 'nocturnal_start_time',           'value' => '20:00', 'group' => 'attendance'],
            ['key' => 'auto_assign_shift',              'value' => 'true', 'group' => 'attendance'],
            ['key' => 'auto_assign_tolerance_minutes',  'value' => '25',   'group' => 'attendance'],
            ['key' => 'auto_assign_min_days',           'value' => '3',    'group' => 'attendance'],
            ['key' => 'auto_assign_regularity_percent', 'value' => '70',   'group' => 'attendance'],
            ['key' => 'lunch_margin_minutes',           'value' => '15',   'group' => 'attendance'],
            ['key' => 'data_retention_months',          'value' => '2',    'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
