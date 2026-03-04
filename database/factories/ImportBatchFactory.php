<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uploaded_by' => User::factory(),
            'original_filename' => fake()->word().'.csv',
            'stored_path' => 'csv_imports/'.fake()->uuid().'.csv',
            'file_hash' => fake()->unique()->sha256(),
            'status' => 'pending',
            'total_rows' => fake()->numberBetween(10, 500),
            'processed_rows' => 0,
            'failed_rows' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'processed_rows' => $attributes['total_rows'] ?? 100,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'errors' => ['Row 5: invalid format'],
        ]);
    }
}
