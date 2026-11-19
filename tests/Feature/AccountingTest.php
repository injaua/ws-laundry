<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $customer = Customer::create(["name" => "Teste Contabilidade", "phone" => "+258840000011", "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORD-2026-0050",
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "total_price" => 10000,
        ]);
    }

    private function entryPayload(array $pairs): array
    {
        return [
            "entry_date" => now()->toDateString(),
            "description" => "Lançamento de teste",
            "pairs" => $pairs,
        ];
    }

    public function test_cashier_cannot_access_accounting(): void
    {
        $cashier = User::factory()->cashier()->create();

        foreach (["/accounting/accounts", "/accounting/journal-entries", "/accounting/trial-balance", "/accounting/balance-sheet", "/accounting/income-statement", "/accounting/vat-settlement"] as $url) {
            $this->actingAs($cashier)->get($url)->assertForbidden();
        }
    }

    public function test_pgc_chart_is_seeded_and_visible_to_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get("/accounting/accounts")
            ->assertOk()
            ->assertSee("Caixa")
            ->assertSee("Prestação de serviços")
            ->assertSee("Gastos e perdas");
    }

    public function test_balanced_manual_entry_is_recorded(): void
    {
        $admin = User::factory()->create();
        $caixa = Account::findByCode("1.1");
        $banco = Account::findByCode("1.2.1");

        $this->actingAs($admin)->post("/accounting/journal-entries", $this->entryPayload([
            ["debit_account_id" => $caixa->id, "debit_amount" => 2000, "credit_account_id" => $banco->id, "credit_amount" => 2000],
        ]))->assertRedirect(route("accounting.journal-entries.index"))
            ->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "manual");
        $this->assertNotNull($entry);
        // Numbered from the id (auto-increments are not reset between tests on MySQL).
        $this->assertSame(sprintf("LNC-%06d", $entry->id), $entry->entry_number);
        $this->assertSame(2, $entry->lines()->count());
        $this->assertSame(2000.0, (float) $entry->lines->sum("debit"));
        $this->assertSame(2000.0, (float) $entry->lines->sum("credit"));
    }

    public function test_unbalanced_manual_entry_is_rejected(): void
    {
        $admin = User::factory()->create();
        $caixa = Account::findByCode("1.1");
        $banco = Account::findByCode("1.2.1");

        $this->actingAs($admin)->post("/accounting/journal-entries", $this->entryPayload([
            ["debit_account_id" => $caixa->id, "debit_amount" => 2000, "credit_account_id" => $banco->id, "credit_amount" => 1500],
        ]))->assertSessionHasErrors("pairs");

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_entry_using_a_class_account_is_rejected(): void
    {
        $admin = User::factory()->create();
        $class = Account::firstWhere("code", "1");
        $caixa = Account::findByCode("1.1");

        $this->actingAs($admin)->post("/accounting/journal-entries", $this->entryPayload([
            ["debit_account_id" => $class->id, "debit_amount" => 1000, "credit_account_id" => $caixa->id, "credit_amount" => 1000],
        ]))->assertSessionHasErrors("pairs");

        $this->assertSame(0, JournalEntry::count());
    }

    public function test_payment_creates_and_syncs_journal_entry(): void
    {
        $order = $this->order();
        $admin = User::factory()->create();
        $banco = Account::findByCode("1.2.2"); // M-Pesa wallet account
        $servicos = Account::findByCode("7.2");

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 4000,
            "method" => "mpesa",
            "transaction_ref" => "MP4000ACC",
        ])->assertSessionHasNoErrors();

        $payment = $order->payments()->first();
        $entry = JournalEntry::firstWhere("source", "payment");
        $this->assertNotNull($entry);
        $this->assertSame($payment->id, $entry->source_id);

        $debit = $entry->lines()->where("debit", ">", 0)->first();
        $credit = $entry->lines()->where("credit", ">", 0)->first();
        $this->assertSame($banco->id, $debit->account_id);
        $this->assertSame($servicos->id, $credit->account_id);

        // Editing the payment rewrites the same entry
        $this->actingAs($admin)->patch("/payments/{$payment->id}", [
            "paid_at" => now()->toDateString(),
            "amount" => 5000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, JournalEntry::where("source", "payment")->count());
        $entry->load("lines");
        $debit = $entry->lines()->where("debit", ">", 0)->first();
        $this->assertSame(Account::findByCode("1.1")->id, $debit->account_id);
        $this->assertSame(5000.0, (float) $debit->debit);

        // Deleting the payment removes the entry
        $this->actingAs($admin)->delete("/payments/{$payment->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, JournalEntry::where("source", "payment")->count());
        $this->assertSame(0, JournalLine::count());
    }

    public function test_expense_posts_to_linked_category_account(): void
    {
        $admin = User::factory()->create();
        $category = ExpenseCategory::create(["key" => "sabor", "name" => "Sabão", "color" => "info", "account_id" => Account::findByCode("6.3.2.9.9")->id]);
        $caixa = Account::findByCode("1.1");

        $this->actingAs($admin)->post("/expenses", [
            "title" => "Sabão azul",
            "category" => "sabor",
            "amount" => 800,
            "expense_date" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "expense");
        $this->assertNotNull($entry);
        $this->assertSame($category->account_id, $entry->lines()->where("debit", ">", 0)->first()->account_id);
        $this->assertSame($caixa->id, $entry->lines()->where("credit", ">", 0)->first()->account_id);
    }

    public function test_expense_without_account_category_creates_no_entry(): void
    {
        $admin = User::factory()->create();
        ExpenseCategory::create(["key" => "semconta", "name" => "Sem Conta", "color" => "secondary"]);

        $this->actingAs($admin)->post("/expenses", [
            "title" => "Qualquer coisa",
            "category" => "semconta",
            "amount" => 300,
            "expense_date" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, JournalEntry::where("source", "expense")->count());
    }

    public function test_trial_balance_shows_balanced_totals(): void
    {
        $order = $this->order();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 6000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/accounting/trial-balance")
            ->assertOk()
            ->assertSee("Caixa")
            ->assertSee("Prestação de serviços")
            ->assertSee("6,000");
    }

    public function test_account_with_movements_cannot_be_deleted(): void
    {
        $order = $this->order();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 1000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $caixa = Account::findByCode("1.1");

        $this->actingAs($admin)->delete("/accounting/accounts/{$caixa->id}")
            ->assertSessionHas("error", __("app.account_in_use"));

        $this->assertDatabaseHas("accounts", ["id" => $caixa->id]);
    }

    public function test_auto_entry_cannot_be_edited_or_deleted_directly(): void
    {
        $order = $this->order();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 1000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "payment");

        $this->actingAs($admin)->delete("/accounting/journal-entries/{$entry->id}")
            ->assertSessionHas("error", __("app.journal_auto_locked"));
        $this->assertDatabaseHas("journal_entries", ["id" => $entry->id]);

        $this->actingAs($admin)->get("/accounting/journal-entries/{$entry->id}/edit")
            ->assertRedirect(route("accounting.journal-entries.index"));
    }

    public function test_category_without_account_cannot_be_created(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/expense-categories", [
            "name" => "Sabão novo",
            "color" => "info",
            "is_fixed" => 0,
            "is_salary" => 0,
        ])->assertSessionHasErrors("account_id");

        $this->assertDatabaseMissing("expense_categories", ["key" => "sabao-novo"]);
    }

    public function test_seeded_expense_categories_reach_the_trial_balance(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/expenses", [
            "title" => "Detergente azul",
            "category" => "detergent",
            "amount" => 1961,
            "expense_date" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "expense");
        $this->assertNotNull($entry);
        $this->assertSame("6.1.1", Account::find($entry->lines()->where("debit", ">", 0)->first()->account_id)->code);

        $this->actingAs($admin)->get("/accounting/trial-balance")
            ->assertOk()
            ->assertSee("Custo das matérias e mercadorias consumidas")
            ->assertSee("1,961");
    }

    public function test_sync_command_backfills_expenses_without_duplicating(): void
    {
        $legacy = ExpenseCategory::create(["key" => "legada", "name" => "Legada", "color" => "secondary"]);
        Expense::create([
            "title" => "Compra legada",
            "category" => "legada",
            "amount" => 900,
            "expense_date" => now()->toDateString(),
            "user_id" => User::factory()->create()->id,
        ]);

        $this->assertSame(0, JournalEntry::where("source", "expense")->count());

        $legacy->update(["account_id" => Account::findByCode("6.3.2.9.9")->id]);
        $this->artisan("accounting:sync-expenses")->assertSuccessful();
        $this->assertSame(1, JournalEntry::where("source", "expense")->count());
        $this->assertSame(2, JournalLine::count());

        $this->artisan("accounting:sync-expenses")->assertSuccessful();
        $this->assertSame(1, JournalEntry::where("source", "expense")->count());
        $this->assertSame(2, JournalLine::count());
    }

    public function test_balance_sheet_balances_after_payment_and_expense(): void
    {
        $order = $this->order();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 6000, "method" => "cash",
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/expenses", [
            "title" => "Detergente", "category" => "detergent", "amount" => 1160, "expense_date" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        // Gross 1160 → net expense 1000 + input VAT 160 (4.4.3.1). Caixa 6000-1160+160 IVA asset = Activo 5000; Capital = 6000-1000 = 5000
        $this->actingAs($admin)->get("/accounting/balance-sheet")
            ->assertOk()
            ->assertSee("1.1")
            ->assertSee("5,000")
            ->assertSee(__('app.balance_sheet_balanced'), false);
    }

    public function test_income_statement_shows_income_expenses_and_net_result(): void
    {
        $order = $this->order();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 6000, "method" => "cash",
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/expenses", [
            "title" => "Detergente", "category" => "detergent", "amount" => 1160, "expense_date" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/accounting/income-statement")
            ->assertOk()
            ->assertSee("Prestação de serviços")
            ->assertSee("Custo das matérias e mercadorias consumidas")
            ->assertSee("6,000")
            ->assertSee("1,000")
            ->assertSee("5,000");
    }
}
