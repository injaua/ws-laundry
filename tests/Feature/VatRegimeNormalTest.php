<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatRegimeNormalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function category(string $key, string $code, bool $vat): ExpenseCategory
    {
        return ExpenseCategory::create([
            "key" => $key,
            "name" => ucfirst($key),
            "color" => "info",
            "account_id" => Account::findByCode($code)->id,
            "is_vat_applicable" => $vat,
        ]);
    }

    private function order(float $vatRate = 0): Order
    {
        $customer = Customer::create(["name" => "Teste IVA", "phone" => "+258840000022", "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORD-IVA-" . str_pad((string) (Order::where("order_number", "like", "ORD-IVA-%")->count() + 1), 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "subtotal" => 1000,
            "vat_rate" => $vatRate,
            "vat_amount" => 160,
            "total_price" => $vatRate > 0 ? 1160 : 1000,
        ]);
    }

    public function test_vat_applicable_expense_splits_input_vat(): void
    {
        $this->admin();
        $category = $this->category("fornecedor", "6.3.2.9.9", vat: true);

        $expense = Expense::create([
            "title" => "Fatura fornecedor",
            "category" => $category->key,
            "amount" => 1160,
            "expense_date" => now()->toDateString(),
        ]);

        $entry = $expense->postToJournal();
        $this->assertNotNull($entry);

        $lines = $entry->lines()->get();
        $this->assertCount(3, $lines);

        $vatAccount = Account::findByCode("4.4.3.1");
        $caixa = Account::findByCode("1.1");

        $this->assertSame(
            ["net" => 1000.0, "vat" => 160.0],
            [
                "net" => (float) $lines->firstWhere("account_id", $category->account_id)->debit,
                "vat" => (float) $lines->firstWhere("account_id", $vatAccount->id)->debit,
            ]
        );
        $this->assertSame(1160.0, (float) $lines->firstWhere("account_id", $caixa->id)->credit);
    }

    public function test_expense_without_vat_flag_stays_a_simple_pair(): void
    {
        $this->admin();
        $category = $this->category("manual", "6.3.2.9.9", vat: false);

        $expense = Expense::create([
            "title" => "Compra sem IVA",
            "category" => $category->key,
            "amount" => 800,
            "expense_date" => now()->toDateString(),
        ]);

        $entry = $expense->postToJournal();
        $this->assertCount(2, $entry->lines);
        $this->assertSame(0, JournalEntry::whereHas("lines", fn ($q) => $q->where("account_id", Account::findByCode("4.4.3.1")->id))->count());
    }

    public function test_sync_payments_backfills_and_is_idempotent(): void
    {
        $this->admin();
        $order = $this->order(vatRate: 16);

        $payment = Payment::create([
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 1160,
            "method" => "mpesa",
        ]);
        $this->assertSame(0, JournalEntry::where("source", "payment")->count());

        $this->artisan("accounting:sync-payments")->assertSuccessful();
        $entry = JournalEntry::firstWhere("source", "payment");
        $this->assertNotNull($entry);
        $this->assertSame(3, $entry->lines()->count());
        $this->assertSame(160.0, (float) $entry->lines()->where("account_id", Account::findByCode("4.4.3.3")->id)->first()->credit);

        $this->artisan("accounting:sync-payments")->assertSuccessful();
        $this->assertSame(1, JournalEntry::where("source", "payment")->count());
    }

    public function test_settlement_moves_balances_to_vat_payable(): void
    {
        $admin = $this->admin();
        $category = $this->category("fornecedor2", "6.3.2.9.9", vat: true);

        $order = $this->order(vatRate: 16);
        $payment = Payment::create([
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 1160,
            "method" => "cash",
        ]);
        $payment->postToJournal();

        Expense::create([
            "title" => "Fatura compras",
            "category" => $category->key,
            "amount" => 580,
            "expense_date" => now()->toDateString(),
        ])->postToJournal();

        $this->actingAs($admin)->get("/accounting/vat-settlement")
            ->assertOk()
            ->assertSee("IVA liquidado por apurar");

        $this->actingAs($admin)->post("/accounting/vat-settlement", [
            "month" => now()->month,
            "year" => now()->year,
        ])->assertSessionHas("success");

        $settlement = JournalEntry::where("source", "manual")->latest("id")->first();
        $this->assertNotNull($settlement);
        $this->assertTrue($settlement->isManual());

        $outputVatLine = $settlement->lines()->where("account_id", Account::findByCode("4.4.3.3")->id)->first();
        $inputVatLine = $settlement->lines()->where("account_id", Account::findByCode("4.4.3.1")->id)->first();
        $aPagar = $settlement->lines()->where("account_id", Account::findByCode("4.4.3.7")->id)->first();

        $this->assertSame(160.0, (float) $outputVatLine->debit);
        $this->assertSame(80.0, (float) $inputVatLine->credit);
        $this->assertSame(80.0, (float) $aPagar->credit);

        // Balances are cleared: settling again finds nothing to do.
        $this->actingAs($admin)->post("/accounting/vat-settlement", [
            "month" => now()->month,
            "year" => now()->year,
        ])->assertSessionHas("error");
        $this->assertSame(1, JournalEntry::where("source", "manual")->count());
    }

    public function test_settlement_with_more_input_than_output_produces_recoverable_vat(): void
    {
        $admin = $this->admin();
        $category = $this->category("fornecedor3", "6.3.2.9.9", vat: true);

        Expense::create([
            "title" => "Compra grande",
            "category" => $category->key,
            "amount" => 1160,
            "expense_date" => now()->toDateString(),
        ])->postToJournal();

        $this->actingAs($admin)->post("/accounting/vat-settlement", [
            "month" => now()->month,
            "year" => now()->year,
        ])->assertSessionHas("success");

        $settlement = JournalEntry::where("source", "manual")->latest("id")->first();
        $aPagar = $settlement->lines()->where("account_id", Account::findByCode("4.4.3.7")->id)->first();

        $this->assertSame(160.0, (float) $aPagar->debit);
        $this->assertSame(0.0, (float) $aPagar->credit);
    }

    public function test_cashier_cannot_access_iva_settlement(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/accounting/vat-settlement")->assertForbidden();
        $this->actingAs($cashier)->post("/accounting/vat-settlement", ["month" => 11, "year" => 2026])->assertForbidden();
    }
}
