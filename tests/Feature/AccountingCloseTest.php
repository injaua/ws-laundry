<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\TrialBalanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingCloseTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function order(): Order
    {
        static $seq = 0;
        $customer = Customer::create(["name" => "Teste Fecho", "phone" => "+25884000" . str_pad((string) ++$seq, 5, "0", STR_PAD_LEFT), "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORD-CLOSE-" . str_pad((string) $seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "subtotal" => 1000,
            "vat_rate" => 0,
            "vat_amount" => 0,
            "total_price" => 1000,
        ]);
    }

    private function paymentOn(string $date, ?User $admin = null): Payment
    {
        $payment = Payment::create([
            "order_id" => $this->order()->id,
            "paid_at" => $date,
            "amount" => 1000,
            "method" => "cash",
            "user_id" => ($admin ?? $this->admin())->id,
        ]);
        $payment->postToJournal();

        return $payment;
    }

    private function close(User $admin, int $month, int $year)
    {
        return $this->actingAs($admin)->post("/accounting/period-close", ["month" => $month, "year" => $year]);
    }

    public function test_close_archives_ytd_trial_balance(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-01-15", $admin);
        $this->paymentOn("2026-02-10", $admin);

        $this->close($admin, 1, 2026)->assertSessionHas("success");
        $this->close($admin, 2, 2026)->assertSessionHas("success");

        $period = AccountingPeriod::where("year", 2026)->where("month", 2)->first();
        $this->assertNotNull($period);
        $this->assertTrue($period->isClosed());
        $this->assertSame($admin->id, $period->closed_by);

        $archive = $period->archive;
        $this->assertNotNull($archive);
        $this->assertSame("2026-01-01", $archive->from_date->toDateString());
        $this->assertSame("2026-02-28", $archive->to_date->toDateString());

        $live = app(TrialBalanceService::class);
        $totals = $live->totals($live->rows(Carbon::create(2026, 1, 1), Carbon::create(2026, 2, 1)->endOfMonth()));

        $this->assertSame((int) round($totals["debit"]), (int) $archive->total_debit);
        $this->assertSame((int) round($totals["credit"]), (int) $archive->total_credit);
        $this->assertGreaterThan(0, (int) $archive->total_debit);
        $this->assertSame((int) $archive->total_debit, (int) $archive->total_credit);

        // Cumulative: the February archive covers both months' movements.
        $janTotals = $live->totals($live->rows(Carbon::create(2026, 1, 1), Carbon::create(2026, 1, 1)->endOfMonth()));
        $this->assertGreaterThan((int) round($janTotals["debit"]), (int) $archive->total_debit);

        $this->assertSame((int) $archive->total_debit, (int) $archive->lines->sum("total_debit"));
    }

    public function test_close_requires_sequential(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-01-15", $admin);
        $this->paymentOn("2026-02-10", $admin);
        $this->paymentOn("2026-03-05", $admin);

        $this->close($admin, 3, 2026)->assertSessionHas("error");
        $this->assertNull(AccountingPeriod::where("year", 2026)->where("month", 3)->first());
    }

    public function test_close_rejects_future_month(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-01-15", $admin);

        $this->close($admin, 12, 2026)->assertSessionHas("error");
        $this->assertSame(0, AccountingPeriod::where("year", 2026)->where("month", 12)->count());
    }

    public function test_locked_period_rejects_new_and_moved_records(): void
    {
        $admin = $this->admin();
        $payment = $this->paymentOn("2026-01-15", $admin);
        $this->close($admin, 1, 2026);

        $this->actingAs($admin)->post("/accounting/journal-entries", [
            "entry_date" => "2026-01-20",
            "description" => "Manual no período fechado",
        ])->assertSessionHasErrors("entry_date");

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $this->order()->id,
            "paid_at" => "2026-01-10",
            "amount" => 500,
            "method" => "cash",
        ])->assertSessionHasErrors("paid_at");

        $category = ExpenseCategory::create([
            "key" => "close-cat",
            "name" => "Fecho",
            "color" => "info",
            "account_id" => Account::findByCode("6.3.2.9.9")->id,
            "is_vat_applicable" => false,
        ]);

        $this->actingAs($admin)->post("/expenses", [
            "title" => "Despesa no período fechado",
            "category" => $category->key,
            "amount" => 200,
            "expense_date" => "2026-01-10",
        ])->assertSessionHasErrors("expense_date");

        $this->actingAs($admin)->patch("/payments/" . $payment->id, [
            "order_id" => $payment->order_id,
            "paid_at" => "2026-01-20",
            "amount" => 1000,
            "method" => "cash",
        ])->assertSessionHasErrors("paid_at");
    }

    public function test_destroy_in_locked_period_blocked(): void
    {
        $admin = $this->admin();
        $payment = $this->paymentOn("2026-01-15", $admin);
        $category = ExpenseCategory::create([
            "key" => "close-del",
            "name" => "Fecho del",
            "color" => "info",
            "account_id" => Account::findByCode("6.3.2.9.9")->id,
            "is_vat_applicable" => false,
        ]);
        $expense = Expense::create([
            "title" => "Despesa antiga",
            "category" => $category->key,
            "amount" => 300,
            "expense_date" => "2026-01-20",
        ]);

        $this->close($admin, 1, 2026);

        $this->actingAs($admin)->delete("/payments/" . $payment->id)
            ->assertSessionHas("error");
        $this->assertDatabaseHas("payments", ["id" => $payment->id]);

        $this->actingAs($admin)->delete("/expenses/" . $expense->id)
            ->assertSessionHas("error");
        $this->assertDatabaseHas("expenses", ["id" => $expense->id]);
    }

    public function test_iva_settlement_blocked_when_period_closed(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-02-15", $admin);
        $this->close($admin, 2, 2026);

        $this->actingAs($admin)->post("/accounting/vat-settlement", ["month" => 2, "year" => 2026])
            ->assertSessionHas("error");
        $this->assertSame(0, JournalEntry::where("source", "manual")->count());
    }

    public function test_reopen_unarchives_and_allows_edit(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-01-15", $admin);
        $this->close($admin, 1, 2026);

        $period = AccountingPeriod::where("year", 2026)->where("month", 1)->first();
        $this->assertNotNull($period->archive);

        $this->actingAs($admin)->patch("/accounting/period-close/" . $period->id . "/reopen")
            ->assertSessionHas("success");

        $period->refresh();
        $this->assertSame("reopened", $period->status);
        $this->assertSame($admin->id, $period->reopened_by);
        $this->assertNull($period->archive()->first());

        $this->actingAs($admin)->post("/accounting/journal-entries", [
            "entry_date" => "2026-01-25",
            "description" => "Agora permitido",
        ])->assertSessionHasErrors("pairs");

        $this->close($admin, 1, 2026)->assertSessionHas("success");
        $this->assertNotNull($period->refresh()->archive);
    }

    public function test_export_returns_xlsx(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-01-15", $admin);
        $this->close($admin, 1, 2026);

        $archive = AccountingPeriod::where("year", 2026)->where("month", 1)->first()->archive;

        $response = $this->actingAs($admin)->get("/accounting/period-close/archive/" . $archive->id . "/export");
        $response->assertOk();
        $this->assertStringContainsString("spreadsheet", $response->headers->get("Content-Type"));
    }

    public function test_sync_commands_skip_locked_periods(): void
    {
        $admin = $this->admin();
        $payment = Payment::create([
            "order_id" => $this->order()->id,
            "paid_at" => "2026-01-10",
            "amount" => 1000,
            "method" => "cash",
            "user_id" => $admin->id,
        ]);

        $this->paymentOn("2026-02-10", $admin);
        // January has no posted entries, so the sequential flow would not offer it;
        // lock it directly as a previously closed period would be.
        AccountingPeriod::create([
            "year" => 2026,
            "month" => 1,
            "status" => "closed",
            "closed_by" => $admin->id,
            "closed_at" => now(),
        ]);

        $this->artisan("accounting:sync-payments")->assertSuccessful();
        $this->assertSame(0, JournalEntry::where("source", "payment")->where("source_id", $payment->id)->count());
        $this->assertSame(1, JournalEntry::where("source", "payment")->count());
    }

    public function test_cashier_cannot_access_period_close(): void
    {
        $cashier = User::factory()->cashier()->create();
        $period = AccountingPeriod::create(["year" => 2026, "month" => 1, "status" => "closed", "closed_at" => now()]);

        $this->actingAs($cashier)->get("/accounting/period-close")->assertForbidden();
        $this->actingAs($cashier)->post("/accounting/period-close", ["month" => 1, "year" => 2026])->assertForbidden();
        $this->actingAs($cashier)->patch("/accounting/period-close/" . $period->id . "/reopen")->assertForbidden();
    }

    public function test_period_close_and_trial_balance_pages_render(): void
    {
        $admin = $this->admin();
        $this->paymentOn("2026-01-15", $admin);
        $this->close($admin, 1, 2026);

        $this->actingAs($admin)->get("/accounting/period-close")->assertOk();
        $this->actingAs($admin)->get("/accounting/trial-balance")->assertOk();

        $archive = AccountingPeriod::where("year", 2026)->where("month", 1)->first()->archive;
        $this->actingAs($admin)->get("/accounting/period-close/archive/" . $archive->id)->assertOk();
    }
}
