<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Matutino', 'Vespertino', 'Nocturno']).'-'.fake()->unique()->numerify('##'),
            'start_time' => '08:00',
            'end_time' => '17:00',
            'crosses_midnight' => false,
            'lunch_required' => false,
            'lunch_duration_minutes' => 0,
            'tolerance_minutes' => 10,
            'overtime_enabled' => false,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 0,
            'is_active' => true,
        ];
    }

    public function nightShift(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Nocturno-'.fake()->unique()->numerify('##'),
            'start_time' => '22:00',
            'end_time' => '06:00',
            'crosses_midnight' => true,
        ]);
    }

    public function withLunch(): static
    {
        return $this->state(fn (array $attributes) => [
            'lunch_required' => true,
            'lunch_start_time' => '12:00',
            'lunch_end_time' => '13:00',
            'lunch_duration_minutes' => 60,
        ]);
    }

    public function withOvertime(): static
    {
        return $this->state(fn (array $attributes) => [
            'overtime_enabled' => true,
            'overtime_min_block_minutes' => 60,
            'max_daily_overtime_minutes' => 240,
        ]);
    }
}
