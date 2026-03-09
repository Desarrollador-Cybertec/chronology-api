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

        // Shifts (breaks are seeded in a later migration once the shift_breaks table exists)
        DB::table('shifts')->updateOrInsert(
            ['name' => 'Horario 1'],
            [
                'start_time' => '07:00',
                'end_time' => '17:00',
                'tolerance_minutes' => 10,
                'crosses_midnight' => false,
                'overtime_enabled' => true,
                'overtime_min_block_minutes' => 30,
                'max_daily_overtime_minutes' => 120,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // System settings
        $settings = [
            ['key' => 'noise_window_minutes', 'value' => '60', 'group' => 'attendance'],
            ['key' => 'diurnal_start_time', 'value' => '06:00', 'group' => 'attendance'],
            ['key' => 'nocturnal_start_time', 'value' => '20:00', 'group' => 'attendance'],
            ['key' => 'auto_assign_shift', 'value' => 'true', 'group' => 'attendance'],
            ['key' => 'auto_assign_tolerance_minutes', 'value' => '35', 'group' => 'attendance'],
            ['key' => 'lunch_margin_minutes', 'value' => '15', 'group' => 'attendance'],
            ['key' => 'data_retention_months', 'value' => '2', 'group' => 'general'],
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

        DB::table('shifts')->where('name', 'Horario 1')->delete();

        DB::table('users')->whereIn('email', [
            'admin@chronology.test',
            'manager@chronology.test',
        ])->delete();
    }
};
