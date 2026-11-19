<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    public function test_default_categories_are_seeded(): void
    {
        $this->assertDatabaseHas("expense_categories", ["key" => "salary", "is_salary" => true]);
        $this->assertDatabaseHas("expense_categories", ["key" => "detergent"]);
        $this->assertDatabaseHas("expense_categories", ["key" => "cleaning_products"]);
        $this->assertDatabaseHas("expense_categories", ["key" => "maintenance"]);
        $this->assertSame(7, ExpenseCategory::count());
    }

    public function test_category_can_be_created_and_used_by_an_expense(): void
    {
        $this->actingAs($this->owner())->post("/expense-categories", [
            "name" => "Renda",
            "color" => "dark",
            "is_fixed" => "1",
            "default_amount" => 15000,
            "is_salary" => "0",
            "account_id" => Account::findByCode("6.3.2.9.9")->id,
        ])->assertRedirect(route("expense-categories.index"));

        $this->assertDatabaseHas("expense_categories", [
            "key" => "renda", "name" => "Renda", "is_fixed" => true, "default_amount" => 15000,
        ]);

        $this->actingAs($this->owner())->post("/expenses", [
            "title" => "Renda de Novembro",
            "category" => "renda",
            "period_type" => "monthly",
            "amount" => 15000,
            "expense_date" => "2026-11-01",
        ])->assertRedirect(route("expenses.index"));

        $this->assertDatabaseHas("expenses", [
            "category" => "renda", "period_type" => "monthly", "amount" => 15000,
        ]);
    }

    public function test_expense_rejects_unknown_category(): void
    {
        $this->actingAs($this->owner())->post("/expenses", [
            "title" => "Teste",
            "category" => "nao-existe",
            "amount" => 100,
            "expense_date" => "2026-11-01",
        ])->assertSessionHasErrors("category");
    }

    public function test_fixed_category_requires_default_amount(): void
    {
        $this->actingAs($this->owner())->post("/expense-categories", [
            "name" => "Luz Fixa",
            "color" => "warning",
            "is_fixed" => "1",
            "is_salary" => "0",
        ])->assertSessionHasErrors("default_amount");
    }

    public function test_period_type_is_dropped_for_non_fixed_categories(): void
    {
        $this->actingAs($this->owner())->post("/expenses", [
            "title" => "Detergente",
            "category" => "detergent",
            "period_type" => "once",
            "amount" => 500,
            "expense_date" => "2026-11-01",
        ])->assertRedirect(route("expenses.index"));

        $this->assertDatabaseHas("expenses", ["category" => "detergent", "period_type" => null]);
    }

    public function test_only_one_salary_category_allowed(): void
    {
        $this->actingAs($this->owner())->post("/expense-categories", [
            "name" => "Ordenados",
            "color" => "success",
            "is_salary" => "1",
            "account_id" => Account::findByCode("6.2.2")->id,
        ])->assertSessionHasErrors("is_salary");
    }

    public function test_category_in_use_cannot_be_deleted_but_unused_can(): void
    {
        Expense::create([
            "title" => "Velha", "category" => "water", "amount" => 100, "expense_date" => "2026-11-01",
        ]);
        $water = ExpenseCategory::firstWhere("key", "water");
        $this->actingAs($this->owner())
            ->delete("/expense-categories/{$water->id}")
            ->assertSessionHas("error", __('app.category_in_use'));
        $this->assertDatabaseHas("expense_categories", ["id" => $water->id]);

        $this->actingAs($this->owner())->post("/expense-categories", [
            "name" => "Temporaria", "color" => "info", "account_id" => Account::findByCode("6.3.2.9.9")->id,
        ]);
        $temp = ExpenseCategory::firstWhere("key", "temporaria");
        $this->actingAs($this->owner())
            ->delete("/expense-categories/{$temp->id}")
            ->assertRedirect(route("expense-categories.index"));
        $this->assertDatabaseMissing("expense_categories", ["id" => $temp->id]);
    }

    public function test_salary_total_endpoint_sums_all_payslips_of_period(): void
    {
        EmployeeSalary::factory()->create([
            "period_year" => 2026, "period_month" => 11,
            "base_salary" => 12000, "bonus" => 0, "deduction" => 0, "status" => "paid",
        ]);
        EmployeeSalary::factory()->create([
            "period_year" => 2026, "period_month" => 11,
            "base_salary" => 8000, "bonus" => 0, "deduction" => 0, "status" => "paid",
        ]);
        EmployeeSalary::factory()->create([
            "period_year" => 2026, "period_month" => 11,
            "base_salary" => 5000, "bonus" => 0, "deduction" => 0, "status" => "pending",
        ]);
        EmployeeSalary::factory()->create([
            "period_year" => 2026, "period_month" => 10,
            "base_salary" => 7000, "bonus" => 0, "deduction" => 0, "status" => "paid",
        ]);

        $this->actingAs($this->owner())
            ->getJson("/expenses/salary-total?month=11&year=2026")
            ->assertOk()
            ->assertExactJson(["amount" => 25000]);
    }

    public function test_index_lists_linked_account_and_form_requires_it(): void
    {
        $this->actingAs($this->owner())->get("/expense-categories")
            ->assertOk()
            ->assertSee("6.1.1")
            ->assertSee("Custo das matérias e mercadorias consumidas");

        $this->actingAs($this->owner())->get("/expense-categories/create")
            ->assertOk()
            ->assertSee('name="account_id"', false)
            ->assertSee("-- Selecione a conta --");
    }

    public function test_cashier_cannot_manage_categories(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/expense-categories")->assertForbidden();
        $this->actingAs($cashier)->post("/expense-categories", [
            "name" => "X", "color" => "dark",
        ])->assertForbidden();
    }
}
