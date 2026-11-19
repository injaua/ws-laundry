<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CashSessionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    public function test_cashier_opens_a_till_with_a_float(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/cash-sessions/current")->assertOk()->assertSee(__("app.opening_float"));

        $this->actingAs($cashier)->post("/cash-sessions", ["opening_float" => 2500])->assertRedirect();

        $session = CashSession::currentFor($cashier);
        $this->assertNotNull($session);
        $this->assertEquals(2500, (float) $session->opening_float);
        $this->actingAs($cashier)->get("/cash-sessions/current")->assertRedirect(route("cash-sessions.show", $session));
    }

    public function test_an_operator_cannot_hold_two_open_tills(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->openTill($cashier);

        $this->actingAs($cashier)->post("/cash-sessions", ["opening_float" => 100])
            ->assertSessionHasErrors("opening_float");

        $this->assertSame(1, CashSession::where("user_id", $cashier->id)->count());
    }

    public function test_cashier_cannot_take_payments_without_an_open_till(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->order();

        $this->actingAs($cashier)->post("/payments", $this->payment($order, 1000, "cash"))
            ->assertRedirect(route("cash-sessions.current"))
            ->assertSessionHas("error", __("app.cash_session_required"));

        $this->assertSame(0, Payment::count());
    }

    public function test_payments_are_linked_to_the_operators_open_till(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier);
        $order = $this->order();

        $this->actingAs($cashier)->post("/payments", $this->payment($order, 1000, "mpesa"))->assertRedirect();

        $this->assertSame($session->id, Payment::first()->cash_session_id);
    }

    public function test_admin_may_take_payments_without_a_till(): void
    {
        $admin = User::factory()->create();
        $order = $this->order();

        $this->actingAs($admin)->post("/payments", $this->payment($order, 1000, "cash"))->assertSessionHasNoErrors();

        $this->assertNull(Payment::first()->cash_session_id);
    }

    public function test_expected_amounts_combine_float_payments_and_movements(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);

        $this->actingAs($cashier)->post("/payments", $this->payment($this->order(5000), 5000, "cash"));
        $this->actingAs($cashier)->post("/payments", $this->payment($this->order(3000), 3000, "emola"));
        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/movements", ["type" => "withdrawal", "amount" => 4000, "reason" => "Cofre"])
            ->assertSessionHasNoErrors();
        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/movements", ["type" => "deposit", "amount" => 500, "reason" => "Troco"])
            ->assertSessionHasNoErrors();

        $expected = $session->fresh()->expectedByMethod();

        $this->assertEquals(2500, $expected["cash"]); // 1000 + 5000 - 4000 + 500
        $this->assertEquals(3000, $expected["emola"]);
        $this->assertEquals(0, $expected["mpesa"]);
    }

    public function test_withdrawal_cannot_exceed_cash_in_drawer(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/movements", ["type" => "withdrawal", "amount" => 1500, "reason" => "Cofre"])
            ->assertSessionHasErrors("amount");

        $this->assertSame(0, $session->movements()->count());
    }

    public function test_matching_count_closes_without_note_or_journal_entry(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);
        $this->actingAs($cashier)->post("/payments", $this->payment($this->order(5000), 5000, "cash"));

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts(["cash" => 6000])])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route("cash-sessions.show", $session));

        $session->refresh();
        $this->assertFalse($session->isOpen());
        $this->assertSame($cashier->id, $session->closed_by);
        $this->assertSame(0.0, $session->totalDifference());
        $this->assertSame(0, JournalEntry::where("source", "cash_session")->count());
    }

    public function test_difference_requires_a_justification(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts(["cash" => 900])])
            ->assertSessionHasErrors("closing_note");

        $this->assertTrue($session->fresh()->isOpen());
        $this->assertSame(0, $session->counts()->count());
    }

    public function test_cash_shortage_is_booked_to_other_losses(): void
    {
        $this->chart();
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", [
            "counted" => $this->counts(["cash" => 700]),
            "closing_note" => "Troco errado",
        ])->assertSessionHasNoErrors();

        $count = $session->counts()->where("method", "cash")->first();
        $this->assertEquals(-300, (float) $count->difference);

        $entry = JournalEntry::where("source", "cash_session")->where("source_id", $session->id)->with("lines.account")->firstOrFail();
        $this->assertEquals(300, (float) $entry->lines->firstWhere("account.code", "6.8.9.9")->debit);
        $this->assertEquals(300, (float) $entry->lines->firstWhere("account.code", "1.1")->credit);
    }

    public function test_cash_surplus_is_booked_to_other_income(): void
    {
        $this->chart();
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", [
            "counted" => $this->counts(["cash" => 1200]),
            "closing_note" => "Cliente não levou troco",
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::where("source", "cash_session")->with("lines.account")->firstOrFail();
        $this->assertEquals(200, (float) $entry->lines->firstWhere("account.code", "1.1")->debit);
        $this->assertEquals(200, (float) $entry->lines->firstWhere("account.code", "7.6.9.9")->credit);
    }

    public function test_non_cash_difference_is_flagged_but_not_booked(): void
    {
        $this->chart();
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier);
        $this->actingAs($cashier)->post("/payments", $this->payment($this->order(2000), 2000, "mpesa"));

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", [
            "counted" => $this->counts(["mpesa" => 1500]),
            "closing_note" => "Verificar extrato M-Pesa",
        ])->assertSessionHasNoErrors();

        $this->assertTrue($session->fresh("counts")->hasDifferences());
        $this->assertSame(0, JournalEntry::where("source", "cash_session")->count());
    }

    public function test_operator_does_not_see_expected_amounts_while_open(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 1000);

        $this->actingAs($cashier)->get("/cash-sessions/{$session->id}")
            ->assertOk()
            ->assertSee(__("app.blind_count_open_help"))
            ->assertDontSee(__("app.expected_now"));

        $this->actingAs(User::factory()->owner()->create())->get("/cash-sessions/{$session->id}")
            ->assertOk()
            ->assertSee(__("app.expected_now"));
    }

    public function test_operators_cannot_see_or_close_each_others_tills(): void
    {
        $session = $this->openTill(User::factory()->cashier()->create());
        $other = User::factory()->cashier()->create();

        $this->actingAs($other)->get("/cash-sessions/{$session->id}")->assertForbidden();
        $this->actingAs($other)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts([])])->assertForbidden();
        $this->actingAs($other)->post("/cash-sessions/{$session->id}/movements", ["type" => "deposit", "amount" => 10, "reason" => "x"])->assertForbidden();
    }

    public function test_manager_can_close_a_till_left_open(): void
    {
        $session = $this->openTill(User::factory()->cashier()->create());
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts([])])
            ->assertSessionHasNoErrors();

        $this->assertSame($owner->id, $session->fresh()->closed_by);
    }

    public function test_closed_till_cannot_be_closed_again_or_moved(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier);
        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts([])]);

        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts([])])->assertForbidden();
        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/movements", ["type" => "deposit", "amount" => 10, "reason" => "x"])->assertForbidden();
        $this->assertSame(count(Payment::METHODS), $session->counts()->count());
    }

    public function test_payments_of_a_closed_till_are_frozen(): void
    {
        $cashier = User::factory()->cashier()->create();
        $session = $this->openTill($cashier);
        $this->actingAs($cashier)->post("/payments", $this->payment($this->order(), 1000, "cash"));
        $payment = Payment::firstOrFail();
        $this->actingAs($cashier)->post("/cash-sessions/{$session->id}/close", ["counted" => $this->counts(["cash" => 1000])]);

        $admin = User::factory()->create();
        $this->actingAs($admin)->patch("/payments/{$payment->id}", [
            "paid_at" => now()->toDateString(), "amount" => 500, "method" => "cash",
        ])->assertSessionHas("error", __("app.payment_cash_session_closed"));
        $this->actingAs($admin)->delete("/payments/{$payment->id}")
            ->assertSessionHas("error", __("app.payment_cash_session_closed"));

        $this->assertEquals(1000, (float) $payment->fresh()->amount);
    }

    public function test_history_is_scoped_to_the_operator(): void
    {
        $mine = User::factory()->cashier()->create();
        $theirs = User::factory()->cashier()->create();
        $a = $this->openTill($mine);
        $b = $this->openTill($theirs);

        $this->actingAs($mine)->get("/cash-sessions")->assertOk()
            ->assertSee($a->label())->assertDontSee($b->label());

        $this->actingAs(User::factory()->create())->get("/cash-sessions")->assertOk()
            ->assertSee($a->label())->assertSee($b->label());
    }

    public function test_payment_form_asks_cashier_to_open_till(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->order();

        $this->actingAs($cashier)->get("/orders/{$order->id}/payment")
            ->assertOk()->assertSee(__("app.cash_session_required"));

        $session = $this->openTill($cashier);
        $this->actingAs($cashier)->get("/orders/{$order->id}/payment")
            ->assertOk()->assertSee($session->label());
    }

    private function counts(array $values): array
    {
        return array_merge(array_fill_keys(Payment::METHODS, 0), $values);
    }

    private function payment(Order $order, int $amount, string $method): array
    {
        return ["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => $amount, "method" => $method,
            "transaction_ref" => Payment::isMobile($method) ? "T" . strtoupper(Str::random(9)) : null];
    }

    private function order(int $total = 10000): Order
    {
        $customer = Customer::create(["name" => "Till Customer", "phone" => "+25884000" . str_pad((string) ++$this->seq, 4, "0", STR_PAD_LEFT), "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORDTILL" . $this->seq,
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => $total,
            "status" => "received",
        ]);
    }

    /** The PGC accounts the till close posts to (seeded by migrations in a fresh DB). */
    private function chart(): void
    {
        foreach (["1.1", "6.8.9.9", "7.6.9.9"] as $code) {
            $this->assertNotNull(Account::findByCode($code), "missing account $code");
        }
    }
}
