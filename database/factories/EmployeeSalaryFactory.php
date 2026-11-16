<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    protected $model = EmployeeSalary::class;

    public function definition(): array
    {
        return [
            "employee_id" => Employee::factory(),
            "period_year" => now()->year,
            "period_month" => fake()->numberBetween(1, 12),
            "base_salary" => fake()->numberBetween(8000, 60000),
            "bonus" => 0,
            "deduction" => 0,
            "status" => "pending",
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            "status" => "paid",
            "paid_at" => now(),
        ]);
    }
}
