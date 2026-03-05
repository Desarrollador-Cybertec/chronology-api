<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeScheduleException>
 */
class EmployeeScheduleExceptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->unique()->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d'),
            'shift_id' => null,
            'is_working_day' => true,
            'reason' => null,
        ];
    }

    public function restDay(string $reason = 'Día de descanso'): static
    {
        return $this->state(fn () => [
            'is_working_day' => false,
            'reason' => $reason,
        ]);
    }

    public function withShift(): static
    {
        return $this->state(fn () => [
            'shift_id' => Shift::factory(),
        ]);
    }
}
