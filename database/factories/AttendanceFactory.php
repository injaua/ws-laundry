<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            "employee_id" => Employee::factory(),
            "attendance_date" => now()->toDateString(),
            "status" => "present",
        ];
    }

    public function absent(): static
    {
        return $this->state(fn () => ["status" => "absent"]);
    }
}
