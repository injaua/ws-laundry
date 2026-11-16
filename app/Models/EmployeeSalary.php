<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    use HasFactory, Loggable;

    protected $fillable = [
        "employee_id", "period_year", "period_month", "base_salary",
        "bonus", "deduction", "absence_deduction", "net_salary", "status", "paid_at", "notes",
    ];

    protected function casts(): array
    {
        return [
            "base_salary" => "decimal:0",
            "bonus" => "decimal:0",
            "deduction" => "decimal:0",
            "absence_deduction" => "decimal:0",
            "net_salary" => "decimal:0",
            "paid_at" => "datetime",
            "period_year" => "integer",
            "period_month" => "integer",
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (EmployeeSalary $salary) {
            $salary->net_salary = (int) $salary->base_salary + (int) $salary->bonus
                - (int) $salary->deduction - (int) $salary->absence_deduction;
        });
    }

    /**
     * Suggested deduction for unjustified absences: absent days x daily rate
     * (base salary / working days of the period, Sundays excluded).
     * Approved justifications already became excused records, so they are ignored.
     *
     * @return array{days: int, daily_rate: int, amount: int}
     */
    public static function absenceDeductionSuggestion(Employee $employee, int $year, int $month): array
    {
        $start = sprintf("%04d-%02d-01", $year, $month);
        $end = Carbon::parse($start)->endOfMonth()->toDateString();

        $days = Attendance::where("employee_id", $employee->id)
            ->where("status", "absent")
            ->whereBetween("attendance_date", [$start, $end])
            ->count();

        $workingDays = max(1, Vacation::workingDays($start, $end));
        $dailyRate = (int) round((int) $employee->base_salary / $workingDays);

        return ["days" => $days, "daily_rate" => $dailyRate, "amount" => $days * $dailyRate];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isPaid(): bool
    {
        return $this->status === "paid";
    }

    public function periodLabel(): string
    {
        return sprintf("%02d/%d", $this->period_month, $this->period_year);
    }

    public function markAsPaid(): void
    {
        $this->update([
            "status" => "paid",
            "paid_at" => now(),
        ]);
    }

    public function unpay(): void
    {
        $this->update([
            "status" => "pending",
            "paid_at" => null,
        ]);
    }
}
