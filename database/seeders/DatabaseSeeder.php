<?php

namespace Database\Seeders;

use App\Models\Employee;
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
        User::factory()->superadmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@chronology.test',
        ]);

        User::factory()->manager()->create([
            'name' => 'Manager User',
            'email' => 'manager@chronology.test',
        ]);

        $shifts = Shift::factory()->count(3)->sequence(
            [
                'name' => 'Matutino',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'tolerance_minutes' => 10,
                'lunch_required' => true,
                'lunch_duration_minutes' => 60,
            ],
            [
                'name' => 'Vespertino',
                'start_time' => '14:00',
                'end_time' => '22:00',
                'tolerance_minutes' => 10,
                'lunch_required' => true,
                'lunch_duration_minutes' => 60,
            ],
            [
                'name' => 'Nocturno',
                'start_time' => '22:00',
                'end_time' => '06:00',
                'crosses_midnight' => true,
                'tolerance_minutes' => 10,
                'overtime_enabled' => true,
                'overtime_min_block_minutes' => 60,
                'max_daily_overtime_minutes' => 240,
            ],
        )->create();

        $employees = Employee::factory()->count(10)->create();

        $employees->each(function (Employee $employee) use ($shifts) {
            $employee->shiftAssignments()->create([
                'shift_id' => $shifts->random()->id,
                'effective_date' => now()->subDays(30),
            ]);
        });

        // System settings
        $settings = [
            ['key' => 'noise_window_minutes', 'value' => '60', 'group' => 'attendance'],
            ['key' => 'diurnal_start_time', 'value' => '06:00', 'group' => 'attendance'],
            ['key' => 'nocturnal_start_time', 'value' => '20:00', 'group' => 'attendance'],
            ['key' => 'auto_assign_shift', 'value' => 'true', 'group' => 'attendance'],
            ['key' => 'auto_assign_tolerance_minutes', 'value' => '30', 'group' => 'attendance'],
            ['key' => 'data_retention_months', 'value' => '24', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::create($setting);
        }
    }
}
