<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payments, journal entries, payslips and purchases are validated by FormRequests
 * before any controller code runs: bad input never reaches the database.
 */
class CriticalFormRequestsTest extends TestCase
{
    use RefreshDatabase;

    // ---- Payments ----

    public function test_payment_amount_takes_at_most_cents(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => "100.555",
            "method" => "cash",
        ])->assertSessionHasErrors("amount");

        $this->assertSame(0, Payment::count());
    }

    public function test_payment_amount_cannot_overflow_the_column(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => "100000000000",
            "method" => "cash",
        ])->assertSessionHasErrors("amount");
    }

    public function test_payment_for_an_unknown_order_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->post("/payments", [
            "order_id" => 999999,
            "paid_at" => now()->toDateString(),
            "amount" => 100,
            "method" => "cash",
        ])->assertSessionHasErrors("order_id");
    }

    public function test_valid_payment_is_recorded_with_its_normalised_reference(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => "250.50",
            "method" => "transfer",
            "transaction_ref" => " ab 123 ",
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas("payments", ["order_id" => $order->id, "amount" => 250.50, "transaction_ref" => "AB123"]);
    }

    // ---- Journal entries ----

    public function test_journal_entry_needs_at_least_one_row(): void
    {
        $this->actingAs(User::factory()->create())->post("/accounting/journal-entries", [
            "entry_date" => now()->toDateString(),
            "description" => "Sem linhas",
            "pairs" => [["debit_account_id" => "", "debit_amount" => "", "credit_account_id" => "", "credit_amount" => ""]],
        ])->assertSessionHasErrors(["pairs" => __("app.journal_rows_required")]);
    }

    public function test_journal_entry_rejects_amounts_that_are_not_numbers(): void
    {
        [$caixa, $banco] = [Account::findByCode("1.1"), Account::findByCode("1.2.1")];

        $this->actingAs(User::factory()->create())->post("/accounting/journal-entries", [
            "entry_date" => now()->toDateString(),
            "description" => "Valor inválido",
            "pairs" => [["debit_account_id" => $caixa->id, "debit_amount" => "abc", "credit_account_id" => $banco->id, "credit_amount" => "abc"]],
        ])->assertSessionHasErrors(["pairs" => __("app.journal_amount_invalid", ["row" => 1])]);

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_journal_entry_rejects_the_same_account_on_both_sides(): void
    {
        $caixa = Account::findByCode("1.1");

        $this->actingAs(User::factory()->create())->post("/accounting/journal-entries", [
            "entry_date" => now()->toDateString(),
            "description" => "Mesma conta",
            "pairs" => [["debit_account_id" => $caixa->id, "debit_amount" => 100, "credit_account_id" => $caixa->id, "credit_amount" => 100]],
        ])->assertSessionHasErrors(["pairs" => __("app.journal_same_account", ["row" => 1])]);
    }

    public function test_journal_entry_balances_on_the_stored_cents(): void
    {
        [$caixa, $banco] = [Account::findByCode("1.1"), Account::findByCode("1.2.1")];

        $this->actingAs(User::factory()->create())->post("/accounting/journal-entries", [
            "entry_date" => now()->toDateString(),
            "description" => "Cêntimos",
            "pairs" => [
                ["debit_account_id" => $caixa->id, "debit_amount" => "10.10", "credit_account_id" => $banco->id, "credit_amount" => "5.05"],
                ["debit_account_id" => $caixa->id, "debit_amount" => "20.20", "credit_account_id" => $banco->id, "credit_amount" => "25.25"],
            ],
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "manual");
        $this->assertSame(4, $entry->lines()->count());
        $this->assertSame("30.30", number_format((float) $entry->lines()->sum("debit"), 2, ".", ""));
    }

    public function test_cashier_cannot_post_a_journal_entry(): void
    {
        $this->actingAs(User::factory()->cashier()->create())->post("/accounting/journal-entries", [
            "entry_date" => now()->toDateString(),
            "description" => "Sem permissão",
        ])->assertForbidden();
    }

    // ---- Payslips ----

    public function test_deductions_cannot_exceed_what_is_earned(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->owner()->create())->post("/salaries", [
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 10,
            "base_salary" => 10000,
            "bonus" => 500,
            "deduction" => 8000,
            "absence_deduction" => 3000,
        ])->assertSessionHasErrors("deduction");

        $this->assertDatabaseCount("employee_salaries", 0);
    }

    public function test_cashier_cannot_create_a_payslip(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs(User::factory()->cashier()->create())->post("/salaries", [
            "employee_id" => $employee->id,
            "period_year" => 2026,
            "period_month" => 10,
            "base_salary" => 10000,
        ])->assertForbidden();
    }

    // ---- Purchases ----

    public function test_purchase_line_quantity_must_fit_the_column(): void
    {
        $this->actingAs(User::factory()->create())->post("/resources/purchases", [
            "supplier_id" => $this->supplier()->id,
            "order_date" => now()->toDateString(),
            "lines" => [["inventory_item_id" => $this->stockItem()->id, "quantity" => 10000000, "unit_cost" => 10]],
        ])->assertSessionHasErrors("lines.0.quantity");

        $this->assertSame(0, PurchaseOrder::count());
    }

    public function test_purchase_line_subtotal_is_exact(): void
    {
        $this->actingAs(User::factory()->create())->post("/resources/purchases", [
            "supplier_id" => $this->supplier()->id,
            "order_date" => now()->toDateString(),
            "lines" => [["inventory_item_id" => $this->stockItem()->id, "quantity" => "3", "unit_cost" => "33.33"]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas("purchase_order_items", ["subtotal" => 99.99]);
    }

    public function test_receiving_a_line_of_another_purchase_order_is_not_found(): void
    {
        $admin = User::factory()->create();
        $first = $this->purchase($admin);
        $second = $this->purchase($admin);
        $line = $second->items()->first();

        $this->actingAs($admin)
            ->post("/resources/purchases/{$first->id}/items/{$line->id}/receive", ["quantity" => 1])
            ->assertNotFound();
    }

    private function order(): Order
    {
        $customer = Customer::create(["name" => "Cliente Pedido", "phone" => "+258840000201", "address" => "Maputo"]);

        return Order::create([
            "order_number" => "ORD-REQ-1",
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "total_price" => 10000,
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::create(["name" => "Fornecedor Teste", "phone" => "867314599"]);
    }

    private function stockItem(): InventoryItem
    {
        return InventoryItem::create(["kind" => "product", "name" => "Amaciador", "type" => "softener", "unit" => "l"]);
    }

    private function purchase(User $admin): PurchaseOrder
    {
        $this->actingAs($admin)->post("/resources/purchases", [
            "supplier_id" => $this->supplier()->id,
            "order_date" => now()->toDateString(),
            "lines" => [["inventory_item_id" => $this->stockItem()->id, "quantity" => 5, "unit_cost" => 100]],
        ])->assertSessionHasNoErrors();

        $purchase = PurchaseOrder::latest("id")->first();
        $this->actingAs($admin)->patch("/resources/purchases/{$purchase->id}/order");

        return $purchase;
    }
}
