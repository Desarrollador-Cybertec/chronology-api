<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
    }
};

