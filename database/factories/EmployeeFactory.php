<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            "name" => fake()->name(),
            "bi_number" => fake()->optional()->bothify("##/######/##"),
            "position" => fake()->jobTitle(),
            "phone" => "84".fake()->numerify("#######"),
            "hire_date" => fake()->dateTimeBetween("-3 years", "now")->format("Y-m-d"),
            "probation_ends_at" => null,
            "status" => "active",
            "contract_type" => "full_time",
            "base_salary" => fake()->numberBetween(8000, 60000),
        ];
    }

    public function probation(): static
    {
        return $this->state(fn () => ["status" => "probation"]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ["status" => "suspended"]);
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            "status" => "terminated",
            "termination_date" => now()->toDateString(),
            "termination_reason" => "Contrato resolvido por mútuo acordo",
        ]);
    }
}
