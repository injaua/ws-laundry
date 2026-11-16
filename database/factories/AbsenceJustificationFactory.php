<?php

namespace Database\Factories;

use App\Models\AbsenceJustification;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceJustification>
 */
class AbsenceJustificationFactory extends Factory
{
    protected $model = AbsenceJustification::class;

    public function definition(): array
    {
        return [
            "employee_id" => Employee::factory(),
            "absence_date" => now()->toDateString(),
            "reason" => fake()->sentence(4),
            "status" => "pending",
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ["status" => "approved"]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ["status" => "rejected"]);
    }
}
