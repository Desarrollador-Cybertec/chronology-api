<?php

namespace Database\Seeders;

use App\Models\Shift;
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
        // Shifts
        Shift::factory()->create([
            'name' => 'Turno 1',
            'start_time' => '07:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
        ]);
        Shift::factory()->create([
            'name' => 'Turno 2',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'tolerance_minutes' => 10,
        ]);

        Shift::factory()->create([
            'name' => 'Turno 3',
            'start_time' => '13:00',
            'end_time' => '22:00',
            'tolerance_minutes' => 10,
        ]);
    }
}
