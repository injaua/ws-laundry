<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    protected $fillable = [
        "employee_id", "period_year", "period_month", "base_salary",
        "bonus", "deduction", "absence_deduction", "net_salary", "status", "paid_at", "notes",
    ];

    protected function casts(): array
    {
        return [
            "base_salary" => "decimal:2",
            "bonus" => "decimal:2",
            "deduction" => "decimal:2",
            "absence_deduction" => "decimal:2",
            "net_salary" => "decimal:2",
            "paid_at" => "datetime",
            "period_year" => "integer",
            "period_month" => "integer",
        ];
    }

    protected static function booted(): void
    {
        // The author decides who may change the payslip (EmployeeSalaryPolicy).
        static::creating(function (EmployeeSalary $salary) {
            $salary->created_by ??= auth()->id();
        });

        static::saving(function (EmployeeSalary $salary) {
            $salary->net_salary = Money::round((float) $salary->base_salary + (float) $salary->bonus
                - (float) $salary->deduction - (float) $salary->absence_deduction);
        });
    }

    /**
     * Suggested deduction for unjustified absences: absent days x daily rate
     * (base salary / working days of the period, Sundays excluded).
     * Approved justifications already became excused records, so they are ignored.
     *
     * @return array{days: int, daily_rate: float, amount: float}
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
        $dailyRate = Money::round((float) $employee->base_salary / $workingDays);

        return ["days" => $days, "daily_rate" => $dailyRate, "amount" => Money::round($days * $dailyRate)];
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
