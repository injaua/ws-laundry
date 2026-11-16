<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        return [
            "employee_id" => Employee::factory(),
            "leave_date" => now()->toDateString(),
            "hours" => 8,
            "reason" => fake()->sentence(4),
            "status" => "pending",
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ["status" => "approved"]);
    }
}
