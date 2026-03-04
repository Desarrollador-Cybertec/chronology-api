<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RawLog>
 */
class RawLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'employee_id' => Employee::factory(),
            'import_batch_id' => ImportBatch::factory(),
            'check_time' => $date,
            'date_reference' => $date->format('Y-m-d'),
            'original_line' => fake()->numerify('####,2026-01-15 08:0#:##'),
        ];
    }
}
