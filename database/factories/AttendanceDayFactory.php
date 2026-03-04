<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceDay>
 */
class AttendanceDayFactory extends Factory
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
            'date_reference' => $date->format('Y-m-d'),
            'shift_id' => Shift::factory(),
            'first_check_in' => $date->format('Y-m-d').' 08:05:00',
            'last_check_out' => $date->format('Y-m-d').' 17:02:00',
            'worked_minutes' => 537,
            'overtime_minutes' => 0,
            'overtime_diurnal_minutes' => 0,
            'overtime_nocturnal_minutes' => 0,
            'late_minutes' => 5,
            'early_departure_minutes' => 0,
            'status' => 'present',
            'is_manually_edited' => false,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_check_in' => null,
            'last_check_out' => null,
            'worked_minutes' => 0,
            'overtime_minutes' => 0,
            'late_minutes' => 0,
            'status' => 'absent',
        ]);
    }
}
