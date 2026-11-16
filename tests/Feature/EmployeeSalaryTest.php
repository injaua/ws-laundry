<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    public function test_payslip_net_is_computed_server_side(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/salaries", [
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 11,
            "base_salary" => 20000,
            "bonus" => 3000,
            "deduction" => 5000,
        ])->assertRedirect(route("salaries.index"));

        $this->assertDatabaseHas("employee_salaries", [
            "employee_id" => $employee->id,
            "net_salary" => 18000,
            "status" => "pending",
        ]);
    }

    public function test_duplicate_period_for_same_employee_is_rejected(): void
    {
        $employee = Employee::factory()->create();
        EmployeeSalary::factory()->create([
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 5,
        ]);

        $this->actingAs($this->owner())->post("/salaries", [
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 5,
            "base_salary" => 10000,
        ])->assertSessionHasErrors("period_month");

        $this->assertDatabaseCount("employee_salaries", 1);
    }

    public function test_paying_payslip_does_not_post_expense(): void
    {
        $salary = EmployeeSalary::factory()->create([
            "base_salary" => 25000,
            "bonus" => 0,
            "deduction" => 0,
        ]);

        $this->actingAs($this->owner())
            ->patch("/salaries/{$salary->id}/pay")
            ->assertRedirect()
            ->assertSessionHas("success", __('app.salary_paid'));

        $salary->refresh();
        $this->assertSame("paid", $salary->status);
        $this->assertNotNull($salary->paid_at);
        $this->assertSame(0, Expense::count());
    }

    public function test_unpaying_reverts_status_without_touching_expenses(): void
    {
        $salary = EmployeeSalary::factory()->create(["base_salary" => 30000, "bonus" => 0, "deduction" => 0]);
        $salary->markAsPaid();

        $this->actingAs($this->owner())
            ->patch("/salaries/{$salary->id}/unpay")
            ->assertSessionHas("success", __('app.salary_unpaid'));

        $salary->refresh();
        $this->assertSame("pending", $salary->status);
        $this->assertNull($salary->paid_at);
        $this->assertSame(0, Expense::count());
    }

    public function test_paid_payslip_cannot_be_edited_or_deleted(): void
    {
        $salary = EmployeeSalary::factory()->create(["base_salary" => 10000, "bonus" => 0, "deduction" => 0]);
        $salary->markAsPaid();
        $owner = $this->owner();

        $this->actingAs($owner)->get("/salaries/{$salary->id}/edit")
            ->assertRedirect(route("salaries.index"))
            ->assertSessionHas("error", __('app.cannot_edit_paid_payslip'));

        $this->actingAs($owner)->put("/salaries/{$salary->id}", [
            "employee_id" => $salary->employee_id,
            "period_year" => $salary->period_year,
            "period_month" => $salary->period_month,
            "base_salary" => 99999,
        ])->assertSessionHas("error", __('app.cannot_edit_paid_payslip'));

        $this->actingAs($owner)->delete("/salaries/{$salary->id}")
            ->assertSessionHas("error", __('app.cannot_delete_paid_payslip'));

        $this->assertDatabaseHas("employee_salaries", ["id" => $salary->id, "status" => "paid"]);
    }

    public function test_pending_payslip_can_be_deleted(): void
    {
        $salary = EmployeeSalary::factory()->create();

        $this->actingAs($this->owner())
            ->delete("/salaries/{$salary->id}")
            ->assertRedirect(route("salaries.index"));

        $this->assertDatabaseMissing("employee_salaries", ["id" => $salary->id]);
    }

    public function test_cashier_cannot_manage_salaries(): void
    {
        $cashier = User::factory()->cashier()->create();
        $salary = EmployeeSalary::factory()->create();

        $this->actingAs($cashier)->get("/salaries")->assertForbidden();
        $this->actingAs($cashier)->patch("/salaries/{$salary->id}/pay")->assertForbidden();
        $this->assertSame("pending", $salary->fresh()->status);
    }

    public function test_salary_summary_lists_employees_and_totals_for_period(): void
    {
        $paid = EmployeeSalary::factory()->create([
            "period_year" => 2026, "period_month" => 11,
            "base_salary" => 25000, "bonus" => 0, "deduction" => 0,
        ]);
        $paid->markAsPaid();
        $pending = EmployeeSalary::factory()->create([
            "period_year" => 2026, "period_month" => 11,
            "base_salary" => 15000, "bonus" => 2000, "deduction" => 0,
        ]);
        $otherMonth = EmployeeSalary::factory()->create(["period_year" => 2026, "period_month" => 10]);

        $response = $this->actingAs($this->owner())->get("/salaries/summary?month=11&year=2026")
            ->assertOk();

        $response->assertSee($paid->employee->name)
            ->assertSee($pending->employee->name)
            ->assertDontSee($otherMonth->employee->name)
            ->assertSee(number_format($pending->net_salary, 0, ".", ","))
            ->assertSee(number_format(25000, 0, ".", ","))
            ->assertSee(number_format(42000, 0, ".", ","))
            ->assertSee(number_format(25000, 0, ".", ","))
            ->assertSee(number_format(17000, 0, ".", ","));
    }

    public function test_salary_summary_defaults_to_current_month(): void
    {
        $current = EmployeeSalary::factory()->create([
            "period_year" => now()->year, "period_month" => now()->month,
        ]);
        $old = EmployeeSalary::factory()->create([
            "period_year" => now()->year - 1, "period_month" => now()->month,
        ]);

        $this->actingAs($this->owner())->get("/salaries/summary")
            ->assertOk()
            ->assertSee($current->employee->name)
            ->assertDontSee($old->employee->name);
    }

    public function test_cashier_cannot_access_salary_summary(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/salaries/summary")->assertForbidden();
    }

    public function test_paying_already_paid_payslip_returns_404(): void
    {
        $salary = EmployeeSalary::factory()->create(["base_salary" => 5000, "bonus" => 0, "deduction" => 0]);
        $salary->markAsPaid();

        $this->actingAs($this->owner())
            ->patch("/salaries/{$salary->id}/pay")
            ->assertNotFound();

        $this->assertSame(0, Expense::count());
    }

    public function test_absence_deduction_lands_in_net(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/salaries", [
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 11,
            "base_salary" => 20000,
            "bonus" => 3000,
            "deduction" => 5000,
            "absence_deduction" => 1600,
        ])->assertRedirect(route("salaries.index"));

        $this->assertDatabaseHas("employee_salaries", [
            "employee_id" => $employee->id,
            "absence_deduction" => 1600,
            "net_salary" => 16400,
        ]);
    }

    public function test_absence_suggestion_counts_unjustified_absent_days(): void
    {
        // November 2026 has 25 working days (Sundays excluded), so 20 000 / 25 = 800/day
        $employee = Employee::factory()->create(["base_salary" => 20000]);
        foreach (["2026-11-03", "2026-11-04"] as $date) {
            Attendance::factory()->create([
                "employee_id" => $employee->id, "attendance_date" => $date, "status" => "absent",
            ]);
        }
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-11-05", "status" => "excused",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-11-06", "status" => "present",
        ]);

        $this->actingAs($this->owner())
            ->getJson("/salaries/absence-suggestion?employee_id={$employee->id}&period_year=2026&period_month=11")
            ->assertOk()
            ->assertExactJson(["days" => 2, "daily_rate" => 800, "amount" => 1600]);
    }
}
