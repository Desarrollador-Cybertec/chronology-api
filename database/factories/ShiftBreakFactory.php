<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShiftBreak>
 */
class ShiftBreakFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shift_id' => Shift::factory(),
            'type' => 'lunch',
            'start_time' => '12:00',
            'end_time' => '13:00',
            'duration_minutes' => 60,
            'position' => 0,
        ];
    }

    public function morningSnack(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'morning_snack',
            'start_time' => '09:30',
            'end_time' => '09:45',
            'duration_minutes' => 15,
            'position' => 0,
        ]);
    }

    public function lunch(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'lunch',
            'start_time' => '12:00',
            'end_time' => '12:30',
            'duration_minutes' => 30,
            'position' => 1,
        ]);
    }

    public function afternoonSnack(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'afternoon_snack',
            'start_time' => '15:00',
            'end_time' => '15:15',
            'duration_minutes' => 15,
            'position' => 2,
        ]);
    }
}
