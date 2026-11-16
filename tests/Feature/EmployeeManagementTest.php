<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function employeeData(array $overrides = []): array
    {
        return array_merge([
            "name" => "Teste Funcionário",
            "position" => "Guarda",
            "hire_date" => "2026-11-01",
            "contract_type" => "full_time",
            "base_salary" => 12000,
        ], $overrides);
    }

    public function test_owner_and_admin_can_view_employees_index(): void
    {
        Employee::factory()->create(["name" => "Bernardo Simbine"]);

        $this->actingAs($this->owner())->get("/employees")
            ->assertOk()->assertSee("Bernardo Simbine", false);
        $this->actingAs($this->admin())->get("/employees")->assertOk();
    }

    public function test_cashier_cannot_access_employee_management(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/employees")->assertForbidden();
        $this->actingAs($cashier)->post("/employees", $this->employeeData())->assertForbidden();
        $this->assertDatabaseCount("employees", 0);
    }

    public function test_employee_can_be_hired_without_system_account(): void
    {
        $this->actingAs($this->owner())->post("/employees", $this->employeeData())
            ->assertRedirect(route("employees.index"))
            ->assertSessionHas("success", __('app.employee_created'));

        $this->assertDatabaseHas("employees", [
            "name" => "Teste Funcionário",
            "position" => "Guarda",
            "status" => "probation",
            "user_id" => null,
        ]);
    }

    public function test_employee_can_be_linked_to_system_user(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($this->owner())->post("/employees", $this->employeeData(["user_id" => $cashier->id]))
            ->assertRedirect(route("employees.index"));

        $this->assertDatabaseHas("employees", ["user_id" => $cashier->id]);
    }

    public function test_user_can_only_be_linked_to_one_employee(): void
    {
        $cashier = User::factory()->cashier()->create();
        Employee::factory()->create(["user_id" => $cashier->id]);

        $this->actingAs($this->owner())
            ->post("/employees", $this->employeeData(["user_id" => $cashier->id]))
            ->assertSessionHasErrors("user_id");

        $this->assertDatabaseCount("employees", 1);
    }

    public function test_employee_can_be_updated(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())
            ->put("/employees/{$employee->id}", $this->employeeData([
                "name" => "Nome Atualizado",
                "base_salary" => 15000,
            ]))
            ->assertRedirect(route("employees.show", $employee));

        $this->assertDatabaseHas("employees", [
            "id" => $employee->id,
            "name" => "Nome Atualizado",
            "base_salary" => 15000,
        ]);
    }

    public function test_probation_can_be_confirmed_as_active(): void
    {
        $employee = Employee::factory()->probation()->create();

        $this->actingAs($this->owner())
            ->patch("/employees/{$employee->id}/status", ["status" => "active"])
            ->assertRedirect();

        $this->assertSame("active", $employee->fresh()->status);
    }

    public function test_active_employee_can_be_suspended_and_reactivated(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())
            ->patch("/employees/{$employee->id}/status", ["status" => "suspended"]);
        $this->assertSame("suspended", $employee->fresh()->status);

        $this->actingAs($this->owner())
            ->patch("/employees/{$employee->id}/status", ["status" => "active"]);
        $this->assertSame("active", $employee->fresh()->status);
    }

    public function test_terminated_employee_cannot_change_status(): void
    {
        $employee = Employee::factory()->terminated()->create();

        $this->actingAs($this->owner())
            ->patch("/employees/{$employee->id}/status", ["status" => "active"])
            ->assertSessionHasErrors("status");

        $this->assertSame("terminated", $employee->fresh()->status);
    }

    public function test_termination_requires_date_and_reason(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())
            ->patch("/employees/{$employee->id}/terminate", [])
            ->assertSessionHasErrors(["termination_date", "termination_reason"]);

        $this->actingAs($this->admin())
            ->patch("/employees/{$employee->id}/terminate", [
                "termination_date" => "2026-11-30",
                "termination_reason" => "Fim de contrato",
            ])->assertRedirect(route("employees.show", $employee));

        $fresh = $employee->fresh();
        $this->assertSame("terminated", $fresh->status);
        $this->assertSame("Fim de contrato", $fresh->termination_reason);
    }

    public function test_employee_can_be_deleted(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())
            ->delete("/employees/{$employee->id}")
            ->assertRedirect(route("employees.index"));

        $this->assertDatabaseMissing("employees", ["id" => $employee->id]);
    }

    public function test_employee_profile_shows_linked_records(): void
    {
        $employee = Employee::factory()->create(["name" => "Ana Mucari"]);
        EmployeeSalary::factory()->create([
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 10,
        ]);

        $this->actingAs($this->owner())
            ->get("/employees/{$employee->id}")
            ->assertOk()
            ->assertSee("Ana Mucari", false)
            ->assertSee("10/2026", false)
            ->assertSee(__('app.days_present'), false);
    }

    public function test_cashier_self_read_shows_only_own_payslips(): void
    {
        $cashier = User::factory()->cashier()->create();
        $mine = Employee::factory()->create(["user_id" => $cashier->id, "name" => "Eu Caixa"]);
        $other = Employee::factory()->create(["name" => "Outro Funcionário"]);

        EmployeeSalary::factory()->create([
            "employee_id" => $mine->id,
            "period_year" => 2026,
            "period_month" => 9,
            "base_salary" => 12345,
            "bonus" => 0,
            "deduction" => 0,
        ]);
        EmployeeSalary::factory()->create([
            "employee_id" => $other->id,
            "period_year" => 2026,
            "period_month" => 9,
            "base_salary" => 54321,
            "bonus" => 0,
            "deduction" => 0,
        ]);

        $this->actingAs($cashier)->get("/my-payslips")
            ->assertOk()
            ->assertSee("12,345", false)
            ->assertDontSee("54,321", false);
    }

    public function test_cashier_self_read_without_employee_record(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/my-payslips")
            ->assertOk()
            ->assertSee(__('app.no_employee_record'), false);
        $this->actingAs($cashier)->get("/my-attendance")
            ->assertOk()
            ->assertSee(__('app.no_employee_record'), false);
    }
}
