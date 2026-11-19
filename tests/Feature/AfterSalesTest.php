<?php

namespace Tests\Feature;

use App\Http\Controllers\UnclaimedController;
use App\Models\Account;
use App\Models\Complaint;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\OrderPiece;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\WhatsApp\AutomaticNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AfterSalesTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "16");
    }

    private function order(float $total = 1160, array $overrides = []): Order
    {
        $customer = Customer::create(["name" => "Cliente " . ++$this->seq, "phone" => "8400" . str_pad((string) $this->seq, 5, "0", STR_PAD_LEFT), "address" => "Maputo"]);

        return Order::create(array_merge([
            "order_number" => "AS-" . str_pad((string) $this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "ready",
            "received_at" => now()->toDateString(),
            "delivery_date" => now()->toDateString(),
            "total_price" => $total,
            "subtotal" => round($total / 1.16, 2),
            "vat_rate" => 16,
            "vat_amount" => round($total - $total / 1.16, 2),
        ], $overrides));
    }

    private function pay(User $user, Order $order, float $amount, string $method = "cash"): void
    {
        $this->actingAs($user)->post("/payments", [
            "order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => $amount, "method" => $method,
            "transaction_ref" => in_array($method, Payment::UNIQUE_REF_METHODS, true) ? "T" . strtoupper(Str::random(9)) : null,
        ])->assertSessionHasNoErrors();
    }

    private function issue(User $user, Order $order, array $data)
    {
        return $this->actingAs($user)->post("/orders/{$order->id}/credit-notes", array_merge([
            "reason" => "damage", "description" => "Camisa rasgada", "settlement" => "refund", "refund_method" => "cash",
        ], $data));
    }

    /** @return array<string, array{debit: float, credit: float}> keyed by account code */
    private function lines(string $source, int $id): array
    {
        $entry = JournalEntry::where("source", $source)->where("source_id", $id)->with("lines.account")->firstOrFail();

        return $entry->lines->mapWithKeys(fn ($l) => [$l->account->code => ["debit" => (float) $l->debit, "credit" => (float) $l->credit]])->all();
    }

    public function test_cash_refund_reverses_revenue_and_vat_and_leaves_the_till(): void
    {
        $admin = User::factory()->create();
        $till = $this->openTill($admin, 500);
        $order = $this->order(1160);
        $this->pay($admin, $order, 1160);

        $this->issue($admin, $order, ["amount" => 580])
            ->assertRedirect("/orders/{$order->id}")->assertSessionHas("print_credit_note");

        $note = CreditNote::firstOrFail();
        $this->assertMatchesRegularExpression('#^NC\d{4}/0001$#', $note->number);
        $this->assertSame([500.0, 80.0], [(float) $note->net_amount, (float) $note->vat_amount]);
        $this->assertSame($till->id, $note->cash_session_id);

        $lines = $this->lines("credit_note", $note->id);
        $this->assertSame(500.0, $lines["7.2"]["debit"]);
        $this->assertSame(80.0, $lines["4.4.3.3"]["debit"]);
        $this->assertSame(580.0, $lines["1.1"]["credit"]);

        $order->refresh();
        $this->assertSame(580.0, $order->netTotal());
        $this->assertSame(580.0, $order->netPaid());
        $this->assertSame(0.0, $order->remainingBalance());
        // Opening float 500 + payment 1160 − refund 580.
        $this->assertSame(1080.0, $till->fresh()->expectedByMethod()["cash"]);
    }

    public function test_refund_cannot_exceed_what_was_paid_and_deduction_what_is_owed(): void
    {
        $admin = User::factory()->create();
        $this->openTill($admin);
        $order = $this->order(1000);
        $this->pay($admin, $order, 300);

        $this->issue($admin, $order, ["amount" => 400])->assertSessionHasErrors("amount");
        $this->issue($admin, $order, ["amount" => 800, "settlement" => "deduct"])->assertSessionHasErrors("settlement");
        $this->issue($admin, $order, ["amount" => 1200, "settlement" => "deduct"])->assertSessionHasErrors("amount");

        $this->issue($admin, $order, ["amount" => 700, "settlement" => "deduct"])->assertSessionHasNoErrors();
        $this->assertSame(0.0, $order->fresh()->remainingBalance());
        $this->assertDatabaseCount("credit_notes", 1);
    }

    public function test_deduction_on_a_cash_sale_has_no_journal_entry_and_completes_the_balance(): void
    {
        $admin = User::factory()->create();
        $order = $this->order(1000);

        $this->issue($admin, $order, ["amount" => 200, "settlement" => "deduct"])->assertSessionHasNoErrors();

        $this->assertSame(800.0, $order->fresh()->remainingBalance());
        $this->assertDatabaseMissing("journal_entries", ["source" => "credit_note"]);

        // Paying the reduced balance is accepted, a cent more is not.
        $this->actingAs($admin)->post("/payments", ["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 801, "method" => "mpesa", "transaction_ref" => "TOVER801"])
            ->assertSessionHasErrors("amount");
        $this->pay($admin, $order, 800, "mpesa");
        $this->assertSame("delivering", $order->fresh()->status);
    }

    public function test_credit_sale_deduction_after_delivery_reduces_the_receivable(): void
    {
        SiteSetting::set("vat_rate", "0");
        $admin = User::factory()->create();
        $order = $this->order(1000, ["status" => "completed", "completed_at" => now()->toDateString(), "is_credit" => true, "vat_rate" => 0, "vat_amount" => 0, "subtotal" => 1000, "due_date" => now()->addDays(30)]);
        $order->postCreditSaleToJournal();

        $this->issue($admin, $order, ["amount" => 250, "settlement" => "deduct"])->assertSessionHasNoErrors();

        $lines = $this->lines("credit_note", CreditNote::firstOrFail()->id);
        $this->assertSame(250.0, $lines["7.2"]["debit"]);
        $this->assertSame(250.0, $lines["4.1.1"]["credit"]);
        $this->assertSame(750.0, $order->fresh()->remainingBalance());
        $this->assertTrue(Order::outstandingCredit()->whereKey($order->id)->exists());
    }

    public function test_credit_note_before_delivery_lowers_the_revenue_booked_on_delivery(): void
    {
        SiteSetting::set("vat_rate", "0");
        $admin = User::factory()->create();
        $order = $this->order(1000, ["is_credit" => true, "vat_rate" => 0, "vat_amount" => 0, "subtotal" => 1000, "due_date" => now()->addDays(30)]);

        $this->issue($admin, $order, ["amount" => 300, "settlement" => "deduct"])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing("journal_entries", ["source" => "credit_note"]);

        $order->postCreditSaleToJournal();
        $sale = JournalEntry::where("source", "credit_sale")->with("lines.account")->firstOrFail();
        $this->assertSame(700.0, (float) $sale->lines->firstWhere("account.code", "4.1.1")->debit);
    }

    public function test_cashier_cannot_issue_or_void_credit_notes_but_can_print_them(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->order(1000);
        $note = CreditNote::create([
            "number" => "NC2026/0009", "order_id" => $order->id, "customer_id" => $order->customer_id, "issued_at" => today(),
            "reason" => "other", "description" => "x", "amount" => 100, "net_amount" => 100, "settlement" => "deduct", "user_id" => $cashier->id,
        ]);

        $this->actingAs($cashier)->get("/orders/{$order->id}/credit-notes/create")->assertForbidden();
        $this->issue($cashier, $order, ["amount" => 100, "settlement" => "deduct"])->assertForbidden();
        $this->actingAs($cashier)->post("/credit-notes/{$note->id}/void", ["void_reason" => "x"])->assertForbidden();
        $this->actingAs($cashier)->get("/credit-notes")->assertForbidden();
        $this->actingAs($cashier)->get("/credit-notes/{$note->id}")->assertOk()->assertSee("NC2026/0009");
    }

    public function test_voiding_keeps_the_number_and_removes_the_journal_entry(): void
    {
        $admin = User::factory()->create();
        $this->openTill($admin);
        $order = $this->order(1160);
        $this->pay($admin, $order, 1160);
        $this->issue($admin, $order, ["amount" => 116])->assertSessionHasNoErrors();
        $note = CreditNote::firstOrFail();

        $this->actingAs($admin)->post("/credit-notes/{$note->id}/void", [])->assertSessionHasErrors("void_reason");
        $this->actingAs($admin)->post("/credit-notes/{$note->id}/void", ["void_reason" => "Valor errado"])->assertRedirect();

        $this->assertTrue($note->fresh()->isVoided());
        $this->assertDatabaseMissing("journal_entries", ["source" => "credit_note", "source_id" => $note->id]);
        $this->assertSame(1160.0, $order->fresh()->netPaid());

        // The next note does not reuse the voided number.
        $this->issue($admin, $order, ["amount" => 116])->assertSessionHasNoErrors();
        $this->assertStringEndsWith("/0002", CreditNote::latest("id")->first()->number);
    }

    public function test_cash_refund_needs_an_open_till_and_cannot_be_voided_after_closing(): void
    {
        $admin = User::factory()->create();
        $order = $this->order(1000);
        $this->pay($admin, $order, 1000, "mpesa");

        $this->issue($admin, $order, ["amount" => 100])->assertSessionHasErrors("refund_method");
        $this->issue($admin, $order, ["amount" => 100, "refund_method" => "mpesa"])->assertSessionHasNoErrors();

        $till = $this->openTill($admin);
        $this->issue($admin, $order, ["amount" => 100])->assertSessionHasNoErrors();
        $cashNote = CreditNote::where("refund_method", "cash")->firstOrFail();
        $till->update(["status" => "closed", "closed_at" => now()]);

        $this->actingAs($admin)->post("/credit-notes/{$cashNote->id}/void", ["void_reason" => "x"])
            ->assertSessionHas("error", __("app.cn_till_closed"));
        $this->assertFalse($cashNote->fresh()->isVoided());
    }

    public function test_a_refunded_payment_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $order = $this->order(1000);
        $this->pay($admin, $order, 1000, "transfer");
        $this->issue($admin, $order, ["amount" => 400, "refund_method" => "transfer"])->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete("/payments/{$order->payments()->first()->id}")
            ->assertSessionHas("error", __("app.payment_refunded_locked"));
        $this->assertDatabaseCount("payments", 1);
    }

    public function test_refunds_are_subtracted_from_dashboard_revenue(): void
    {
        $admin = User::factory()->create();
        $order = $this->order(1000);
        $this->pay($admin, $order, 1000, "transfer");
        $this->issue($admin, $order, ["amount" => 250, "refund_method" => "transfer"])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/dashboard")->assertOk()->assertViewHas("todayRevenue", 750.0);
    }

    public function test_complaint_is_registered_linked_to_a_piece_and_resolved_by_a_credit_note(): void
    {
        $cashier = User::factory()->cashier()->create();
        $admin = User::factory()->create();
        $order = $this->order(1000);
        $piece = OrderPiece::create(["order_id" => $order->id, "seq" => 1, "code" => "AS-0001-01", "description" => "Camisa", "status" => "ready", "status_at" => now()]);

        $this->actingAs($cashier)->post("/complaints", [
            "order_id" => $order->id, "order_piece_id" => $piece->id, "channel" => "counter", "category" => "stain", "description" => "Mancha de óleo",
        ])->assertRedirect();

        $complaint = Complaint::firstOrFail();
        $this->assertMatchesRegularExpression('#^REC\d{4}/0001$#', $complaint->number);
        $this->assertSame([$order->customer_id, "open"], [$complaint->customer_id, $complaint->status]);
        $this->assertTrue($complaint->due_at->isSameDay(today()->addDays(Complaint::DEADLINE_DAYS)));
        $this->assertStringContainsString($complaint->number, $piece->fresh()->issue);

        $this->actingAs($cashier)->get("/complaints")->assertOk()->assertSee($complaint->number);
        $this->actingAs($cashier)->get("/orders/{$order->id}")->assertOk()->assertSee($complaint->number);

        $this->issue($admin, $order, ["amount" => 200, "settlement" => "deduct", "complaint_id" => $complaint->id])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame(["resolved", "credit_note"], [$complaint->status, $complaint->resolution]);
        $this->assertSame($complaint->id, CreditNote::firstOrFail()->complaint_id);
    }

    public function test_closing_a_complaint_requires_a_resolution(): void
    {
        $user = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Ana", "phone" => "841112233", "address" => "Maputo"]);
        $this->actingAs($user)->post("/complaints", ["customer_id" => $customer->id, "channel" => "phone", "category" => "delay", "description" => "Atraso"]);
        $complaint = Complaint::firstOrFail();

        $this->actingAs($user)->put("/complaints/{$complaint->id}", ["status" => "resolved"])->assertSessionHasErrors("resolution");
        $this->actingAs($user)->put("/complaints/{$complaint->id}", ["status" => "resolved", "resolution" => "explanation", "resolution_notes" => "Explicado"])
            ->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame($user->id, $complaint->resolved_by);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertFalse($complaint->isLate());
    }

    public function test_unclaimed_list_ages_orders_and_only_managers_dispose_after_the_waiting_period(): void
    {
        $cashier = User::factory()->cashier()->create();
        $admin = User::factory()->create();
        $recent = $this->order(500);
        $recent->update(["ready_at" => now()->subDays(10)]);
        $old = $this->order(800);
        $old->update(["ready_at" => now()->subDays(120)]);

        $this->actingAs($cashier)->get("/unclaimed")->assertOk()->assertSee($recent->order_number)->assertSee($old->order_number);

        $this->actingAs($cashier)->post("/orders/{$old->id}/unclaimed/abandon", ["abandon_destination" => "donated"])->assertForbidden();
        $this->actingAs($admin)->post("/orders/{$recent->id}/unclaimed/abandon", ["abandon_destination" => "donated"])
            ->assertSessionHas("error", __("app.unclaimed_too_early", ["days" => 90]));
        $this->actingAs($admin)->post("/orders/{$old->id}/unclaimed/abandon", ["abandon_destination" => "donated", "abandon_note" => "Igreja local"])
            ->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame(["donated", $admin->id], [$old->abandon_destination, $old->abandoned_by]);
        $this->actingAs($admin)->get("/unclaimed")->assertDontSee(">{$old->order_number}</a>", false);
        $this->actingAs($admin)->get("/unclaimed?tab=abandoned")->assertSee($old->order_number);

        $this->actingAs($admin)->post("/orders/{$old->id}/unclaimed/restore")->assertRedirect();
        $this->assertNull($old->fresh()->abandoned_at);
    }

    public function test_abandoned_orders_no_longer_get_automatic_reminders(): void
    {
        $order = $this->order(500);
        $order->update(["ready_at" => now()->subDays(7), "abandoned_at" => now(), "abandon_destination" => "kept"]);

        $this->assertSame(0, Order::unclaimed()->count());
        $this->assertSame(0, Order::where("status", "ready")->whereNull("abandoned_at")->count());
        $this->assertTrue(class_exists(AutomaticNotifications::class));
    }

    public function test_abandon_threshold_is_configurable_by_managers(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post("/unclaimed/settings", ["unclaimed_abandon_days" => 3])->assertSessionHasErrors("unclaimed_abandon_days");
        $this->actingAs($admin)->post("/unclaimed/settings", ["unclaimed_abandon_days" => 60])->assertSessionHasNoErrors();

        $this->assertSame(60, UnclaimedController::abandonDays());
        $this->actingAs(User::factory()->cashier()->create())->post("/unclaimed/settings", ["unclaimed_abandon_days" => 30])->assertForbidden();
    }

    public function test_after_sales_pages_render(): void
    {
        $admin = User::factory()->create();
        $this->openTill($admin);
        $order = $this->order(1160);
        $this->pay($admin, $order, 1160);
        $this->issue($admin, $order, ["amount" => 116])->assertSessionHasNoErrors();
        $note = CreditNote::firstOrFail();

        $this->actingAs($admin)->get("/orders/{$order->id}/credit-notes/create")->assertOk()->assertSee(__("app.cn_settlement_refund"));
        $this->actingAs($admin)->get("/orders/{$order->id}")->assertOk()->assertSee("id=\"after-sales\"", false)->assertSee($note->number)->assertSee(__("app.refunds"));
        $this->actingAs($admin)->get("/credit-notes")->assertOk()->assertSee($note->number);
        $this->actingAs($admin)->get("/credit-notes/{$note->id}?w=58")->assertOk()->assertSee(mb_strtoupper(__("app.credit_note")));
        $this->actingAs($admin)->get("/complaints/create?order={$order->id}")->assertOk();
        $this->actingAs($admin)->get("/orders/{$order->id}/invoice")->assertOk();
    }
}
