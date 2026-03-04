<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Shift;
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
            ['name' => 'Matutino', 'start_time' => '06:00', 'end_time' => '14:00'],
            ['name' => 'Vespertino', 'start_time' => '14:00', 'end_time' => '22:00'],
            ['name' => 'Nocturno', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true],
        )->create();

        $employees = Employee::factory()->count(10)->create();

        $employees->each(function (Employee $employee) use ($shifts) {
            $employee->shiftAssignments()->create([
                'shift_id' => $shifts->random()->id,
                'effective_date' => now()->subDays(30),
            ]);
        });
    }
}
