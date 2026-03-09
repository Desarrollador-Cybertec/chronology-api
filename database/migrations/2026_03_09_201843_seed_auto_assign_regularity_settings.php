<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'auto_assign_min_days', 'value' => '3', 'group' => 'attendance'],
            ['key' => 'auto_assign_regularity_percent', 'value' => '70', 'group' => 'attendance'],
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
            'auto_assign_min_days',
            'auto_assign_regularity_percent',
        ])->delete();
    }
};
