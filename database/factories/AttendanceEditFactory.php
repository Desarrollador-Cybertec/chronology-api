<?php

namespace Database\Factories;

use App\Models\AttendanceDay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceEdit>
 */
class AttendanceEditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_day_id' => AttendanceDay::factory(),
            'edited_by' => User::factory(),
            'field_changed' => fake()->randomElement(['first_check_in', 'last_check_out', 'status']),
            'old_value' => '08:05:00',
            'new_value' => '08:00:00',
            'reason' => fake()->sentence(),
        ];
    }
}
