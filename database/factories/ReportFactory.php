<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'generated_by' => User::factory(),
            'employee_id' => null,
            'type' => 'general',
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'status' => 'pending',
            'summary' => null,
            'rows' => null,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn () => [
            'type' => 'individual',
            'employee_id' => \App\Models\Employee::factory(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'summary' => [],
            'rows' => [],
            'completed_at' => now(),
        ]);
    }
}
