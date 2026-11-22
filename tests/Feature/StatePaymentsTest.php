<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_page_shows_vat_and_inss_payable_for_the_month(): void
    {
        $admin = $this->admin();

        // Output VAT 160 (sale of 1160 at 16%), deductible VAT 50 (purchase of 1050 at 5%).
        $customer = Customer::create(["name" => "Cliente Estado", "phone" => "+258840000077", "address" => "Maputo"]);
        $order = Order::create([
            "order_number" => "ORD-EST-0001", "customer_id" => $customer->id, "status" => "washing",
            "received_at" => now()->toDateString(), "subtotal" => 1000, "vat_rate" => 16, "vat_amount" => 160, "total_price" => 1160,
        ]);
        Payment::create(["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 1160, "method" => "cash"])->postToJournal();

        $category = ExpenseCategory::create([
            "key" => "estado", "name" => "Estado", "color" => "info",
            "account_id" => Account::findByCode("6.3.2.9.9")->id, "is_vat_applicable" => false,
        ]);
        Expense::create([
            "title" => "Compra", "category" => $category->key, "amount" => 1050, "vat_rate" => 5,
            "expense_date" => now()->toDateString(),
        ])->postToJournal();

        // INSS on 10 000 + 2 000 bonus − 1 000 absences = 11 000: 330 (3%) + 440 (4%) = 770.
        EmployeeSalary::factory()->create([
            "employee_id" => Employee::factory()->create(["name" => "Ana Operadora"])->id,
            "period_year" => now()->year, "period_month" => now()->month,
            "base_salary" => 10000, "bonus" => 2000, "absence_deduction" => 1000,
        ]);

        $response = $this->actingAs($admin)->get(route("accounting.state-payments"))->assertOk();
        $response->assertViewHas("monthVat", 110.0);
        $response->assertViewHas("inss", ["base" => 11000.0, "employee" => 330.0, "employer" => 440.0, "total" => 770.0]);
        $response->assertSee("Ana Operadora");
    }

    public function test_settlement_does_not_change_the_month_vat_and_payments_are_listed(): void
    {
        $admin = $this->admin();
        $category = ExpenseCategory::create([
            "key" => "estado2", "name" => "Estado 2", "color" => "info",
            "account_id" => Account::findByCode("6.3.2.9.9")->id, "is_vat_applicable" => false,
        ]);
        Expense::create([
            "title" => "Compra", "category" => $category->key, "amount" => 1160, "vat_rate" => 16,
            "expense_date" => now()->toDateString(),
        ])->postToJournal();

        $this->actingAs($admin)->post(route("accounting.vat-settlement.store"), ["month" => now()->month, "year" => now()->year]);

        // Payment of VAT: Debit 4.4.3.7, Credit Caixa.
        JournalEntry::postLines([
            ["account_id" => Account::findByCode("4.4.3.7")->id, "debit" => 25, "credit" => 0],
            ["account_id" => Account::findByCode("1.1")->id, "debit" => 0, "credit" => 25],
        ], now()->toDateString(), "Pagamento IVA", "expense", 999998);

        $this->actingAs($admin)->get(route("accounting.state-payments"))
            ->assertOk()
            ->assertViewHas("monthVat", -160.0)
            ->assertViewHas("paidVat", 25.0)
            ->assertSee("Pagamento IVA");
    }

    public function test_cashier_cannot_open_the_page(): void
    {
        $this->actingAs(User::factory()->cashier()->create())
            ->get(route("accounting.state-payments"))
            ->assertForbidden();
    }
}
