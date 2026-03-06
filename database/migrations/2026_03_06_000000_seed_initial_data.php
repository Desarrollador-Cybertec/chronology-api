<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Users
        DB::table('users')->insertOrIgnore([
            [
                'name' => 'Super Admin',
                'email' => 'admin@chronology.test',
                'password' => Hash::make('test123'),
                'role' => 'superadmin',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Manager User',
                'email' => 'manager@chronology.test',
                'password' => Hash::make('test123'),
                'role' => 'manager',
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Shift
        DB::table('shifts')->insertOrIgnore([
            'name' => 'Jornada Completa',
            'start_time' => '07:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
            'crosses_midnight' => false,
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
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
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'noise_window_minutes', 'diurnal_start_time', 'nocturnal_start_time',
            'auto_assign_shift', 'auto_assign_tolerance_minutes',
            'lunch_margin_minutes', 'data_retention_months',
        ])->delete();

        DB::table('shifts')->where('name', 'Jornada Completa')->delete();

        DB::table('users')->whereIn('email', [
            'admin@chronology.test',
            'manager@chronology.test',
        ])->delete();
    }
};
