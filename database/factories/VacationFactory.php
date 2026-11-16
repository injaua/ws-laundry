<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Vacation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vacation>
 */
class VacationFactory extends Factory
{
    protected $model = Vacation::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween("now", "+2 months")->format("Y-m-d");
        $end = date("Y-m-d", strtotime($start) + 20 * 86400);

        return [
            "employee_id" => Employee::factory(),
            "start_date" => $start,
            "end_date" => $end,
            "days" => Vacation::workingDays($start, $end),
            "type" => "vacation",
            "status" => "pending",
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ["status" => "approved"]);
    }
}
