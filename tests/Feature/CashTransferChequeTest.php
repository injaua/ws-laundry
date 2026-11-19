<?php

namespace Tests\Feature;

use App\Models\BankDeposit;
use App\Models\CashSession;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WalletStatementLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CashTransferChequeTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function customer(string $type = "individual"): Customer
    {
        $n = ++$this->seq;

        return Customer::create([
            "name" => ($type === "business" ? "Empresa " : "Cliente ") . $n,
            "type" => $type,
            "nuit" => $type === "business" ? str_pad((string) (400000000 + $n), 9, "0", STR_PAD_LEFT) : null,
            "phone" => "+25884200" . str_pad((string) $n, 4, "0", STR_PAD_LEFT),
            "is_credit_customer" => $type === "business",
        ]);
    }

    private function order(Customer $customer, float $total, array $overrides = []): Order
    {
        return Order::create(array_merge([
            "order_number" => "PAY-" . str_pad((string) ++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => today()->toDateString(),
            "total_price" => $total,
            "subtotal" => $total,
            "vat_rate" => 0,
            "vat_amount" => 0,
        ], $overrides));
    }

    private function pay(User $user, Order $order, float $amount, string $method, array $extra = [])
    {
        return $this->actingAs($user)->post("/payments", array_merge([
            "order_id" => $order->id, "paid_at" => today()->toDateString(), "amount" => $amount, "method" => $method,
        ], $extra));
    }

    private function debitAccount(string $source, int $id): ?string
    {
        $entry = JournalEntry::where("source", $source)->where("source_id", $id)->with("lines.account")->first();

        return $entry?->lines->firstWhere(fn ($l) => (float) $l->debit > 0)?->account?->code;
    }

    // ---------- Cash ----------

    public function test_cash_change_is_kept_and_printed(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $order = $this->order($this->customer(), 850);

        $this->pay($admin, $order, 850, "cash", ["tendered" => 500])->assertSessionHasErrors("tendered");
        $this->pay($admin, $order, 850, "cash", ["tendered" => 1000])->assertSessionHasNoErrors()
            ->assertSessionHas("success", __("app.payment_recorded_change", ["change" => money(150)]));

        $payment = Payment::first();
        $this->assertSame(150.0, $payment->change());
        $this->actingAs($admin)->get(route("payments.receipt", $payment))->assertOk()->assertSee(__("app.change_due"));
        // The amount handed over is ignored for other methods.
        $this->pay($admin, $this->order($this->customer(), 100), 100, "transfer", ["tendered" => 999, "transaction_ref" => "TRF1"]);
        $this->assertNull(Payment::firstWhere("method", "transfer")->tendered);
    }

    public function test_cashier_is_warned_when_till_exceeds_limit(): void
    {
        SiteSetting::set("cash_till_limit", 1000);
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier, 500);

        $this->pay($cashier, $this->order($this->customer(), 400), 400, "cash")->assertSessionMissing("warning");
        $this->pay($cashier, $this->order($this->customer(), 300), 300, "cash")->assertSessionHas("warning", __("app.cash_over_limit"));
    }

    // ---------- Transfer ----------

    public function test_transfer_needs_a_unique_reference(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $a = $this->order($this->customer(), 1000);
        $b = $this->order($this->customer(), 1000);

        $this->pay($admin, $a, 1000, "transfer")->assertSessionHasErrors("transaction_ref");
        $this->pay($admin, $a, 1000, "transfer", ["transaction_ref" => "ft 2026/551"])->assertSessionHasNoErrors();
        $this->assertSame("FT2026/551", Payment::first()->transaction_ref);
        $this->pay($admin, $b, 1000, "transfer", ["transaction_ref" => "FT2026/551"])->assertSessionHasErrors("transaction_ref");
        $this->assertSame("1.2.1", $this->debitAccount("payment", Payment::first()->id));
    }

    // ---------- Cheques ----------

    public function test_cheque_policy_and_unique_cheques(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $cheque = ["transaction_ref" => "000123", "cheque_bank" => "bci", "cheque_drawer" => "Polana SA"];

        // Default policy: business customers only.
        $this->pay($admin, $this->order($this->customer(), 500), 500, "cheque", $cheque)->assertSessionHasErrors("method");

        $company = $this->customer("business");
        $order = $this->order($company, 500);
        $this->pay($admin, $order, 500, "cheque", $cheque)->assertSessionHasNoErrors();

        $saved = Cheque::firstOrFail();
        $this->assertSame("BCI", $saved->bank);
        $this->assertSame("000123", $saved->number);
        $this->assertSame("in_hand", $saved->status);
        $this->assertEquals(500, (float) $saved->amount);
        $this->assertSame($saved->id, Payment::first()->cheque_id);
        $this->assertSame("1.3", $this->debitAccount("payment", Payment::first()->id));

        // Same cheque again (any spacing / case) is refused.
        $this->pay($admin, $this->order($company, 500), 500, "cheque", ["transaction_ref" => "000 123", "cheque_bank" => "BCI"])->assertSessionHasErrors("transaction_ref");
        // Bank is mandatory.
        $this->pay($admin, $this->order($company, 500), 500, "cheque", ["transaction_ref" => "777"])->assertSessionHasErrors("cheque_bank");

        $this->actingAs($admin)->get(route("payments.receipt", Payment::first()))->assertOk()->assertSee(__("app.cheque_subject_to_clearing"));

        SiteSetting::set("cheque_policy", "off");
        $this->pay($admin, $this->order($company, 500), 500, "cheque", ["transaction_ref" => "888", "cheque_bank" => "BCI"])->assertSessionHasErrors("method");
    }

    public function test_one_cheque_settles_several_credit_orders(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $company = $this->customer("business");
        $completed = ["status" => "completed", "is_credit" => true, "completed_at" => today()->toDateString()];
        $this->order($company, 3000, $completed);
        $this->order($company, 2000, $completed);

        $this->actingAs($admin)->post(route("customers.payments.store", $company), [
            "paid_at" => today()->toDateString(), "amount" => 5000, "method" => "cheque", "transaction_ref" => "445566", "cheque_bank" => "Standard Bank",
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Cheque::count());
        $this->assertEquals(5000, (float) Cheque::first()->amount);
        $this->assertSame(2, Payment::where("cheque_id", Cheque::first()->id)->count());
    }

    public function test_deposit_bounce_and_customer_block(): void
    {
        $cashier = User::factory()->create(["role" => "cashier"]);
        $admin = User::factory()->create(["role" => "admin"]);
        $session = $this->openTill($cashier, 0);
        $company = $this->customer("business");
        $order = $this->order($company, 1200, ["status" => "ready"]);
        $postDatedOrder = $this->order($company, 300);

        $this->pay($cashier, $order, 1200, "cheque", ["transaction_ref" => "991", "cheque_bank" => "Millennium bim"])->assertSessionHasNoErrors();
        $this->pay($cashier, $postDatedOrder, 300, "cheque", ["transaction_ref" => "992", "cheque_bank" => "Millennium bim", "cheque_date" => today()->addDays(20)->toDateString()])->assertSessionHasNoErrors();
        $cheque = Cheque::where("number", "991")->firstOrFail();
        $postDated = Cheque::where("number", "992")->firstOrFail();
        $this->assertTrue($postDated->isPostDated());

        // Still in the open till: cannot go to the bank yet.
        $this->actingAs($admin)->post(route("treasury.deposits.store"), ["deposited_at" => today()->toDateString(), "cheque_ids" => [$cheque->id]])
            ->assertSessionHasErrors("cheque_ids");

        $session->update(["status" => CashSession::STATUS_CLOSED, "closed_at" => now()]);

        // Post-dated cheque must wait.
        $this->actingAs($admin)->post(route("treasury.deposits.store"), ["deposited_at" => today()->toDateString(), "cheque_ids" => [$postDated->id]])
            ->assertSessionHasErrors("cheque_ids");

        $this->actingAs($admin)->get(route("treasury.deposits.create"))->assertOk()->assertSee("991");
        $this->actingAs($admin)->post(route("treasury.deposits.store"), [
            "deposited_at" => today()->toDateString(), "slip_number" => "dep 7781", "cash_amount" => 2000, "cheque_ids" => [$cheque->id],
        ])->assertRedirect();

        $deposit = BankDeposit::firstOrFail();
        $this->assertEquals(3200, (float) $deposit->total);
        $this->assertSame("deposited", $cheque->fresh()->status);
        $lines = JournalEntry::where("source", "bank_deposit")->firstOrFail()->lines()->with("account")->get();
        $this->assertEquals(3200, (float) $lines->firstWhere("account.code", "1.2.1")->debit);
        $this->assertEquals(2000, (float) $lines->firstWhere("account.code", "1.1")->credit);
        $this->assertEquals(1200, (float) $lines->firstWhere("account.code", "1.3")->credit);
        $this->actingAs($admin)->get(route("treasury.deposits.show", $deposit))->assertOk()->assertSee("DEP7781");

        // Editing a deposited cheque payment is locked.
        $payment = Payment::where("cheque_id", $cheque->id)->first();
        $this->actingAs($admin)->delete(route("payments.destroy", $payment))->assertSessionHas("error");

        // The bank returns it.
        $this->actingAs($admin)->post(route("treasury.cheques.bounce", $cheque), ["reason" => "Falta de provisão", "bounced_on" => today()->toDateString()])
            ->assertSessionHas("warning");

        $this->assertSame("bounced", $cheque->fresh()->status);
        $this->assertSame(0, Payment::where("order_id", $order->id)->count()); // hidden by the scope
        $this->assertSame(1, Payment::withoutGlobalScope("not_bounced")->where("order_id", $order->id)->count());
        $this->assertSame(1200.0, $order->fresh()->remainingBalance());
        // Reversal: revenue debited back, bank credited (the cheque had been deposited).
        $reversal = JournalEntry::where("source", "cheque_bounce")->firstOrFail()->lines()->with("account")->get();
        $this->assertEquals(1200, (float) $reversal->firstWhere("account.code", "1.2.1")->credit);
        $this->assertEquals(1200, (float) $reversal->firstWhere("account.code", "7.2")->debit);

        // The customer can no longer pay by cheque.
        $this->pay($admin, $this->order($company, 100), 100, "cheque", ["transaction_ref" => "993", "cheque_bank" => "BCI"])
            ->assertSessionHasErrors("method");

        $this->actingAs($admin)->get(route("orders.show", $order))->assertOk()->assertSee(__("app.cheque_bounced_on_order"));
        $this->actingAs($admin)->get(route("treasury.cheques", ["status" => "bounced"]))->assertOk()->assertSee($order->order_number);
    }

    public function test_cheque_bounced_in_hand_credits_cheques_account_and_clearing(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $company = $this->customer("business");
        $this->pay($admin, $this->order($company, 700), 700, "cheque", ["transaction_ref" => "5001", "cheque_bank" => "Absa"]);
        $this->pay($admin, $this->order($company, 400), 400, "cheque", ["transaction_ref" => "5002", "cheque_bank" => "Absa"]);
        [$first, $second] = Cheque::orderBy("id")->get()->all();

        $this->actingAs($admin)->post(route("treasury.cheques.bounce", $first), ["reason" => "Assinatura", "bounced_on" => today()->toDateString()]);
        $reversal = JournalEntry::where("source", "cheque_bounce")->firstOrFail()->lines()->with("account")->get();
        $this->assertEquals(700, (float) $reversal->firstWhere("account.code", "1.3")->credit);

        // Clearing needs a deposit first.
        $this->actingAs($admin)->post(route("treasury.cheques.clear", $second))->assertSessionHasErrors();
        $this->actingAs($admin)->post(route("treasury.deposits.store"), ["deposited_at" => today()->toDateString(), "cheque_ids" => [$second->id]]);
        $this->actingAs($admin)->post(route("treasury.cheques.clear-many"), ["cheque_ids" => [$second->id]]);
        $this->assertSame("cleared", $second->fresh()->status);
        $this->assertFalse($second->fresh()->canBounce());

        $this->actingAs($admin)->get(route("treasury.cheques"))->assertOk();
        $this->actingAs($admin)->get(route("treasury.cheques", ["status" => "all"]))->assertOk();
        $this->actingAs($admin)->get(route("treasury.deposits.index"))->assertOk();
    }

    public function test_cheques_are_not_for_couriers_refunds_or_cashiers_treasury(): void
    {
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->actingAs($cashier)->get(route("treasury.cheques"))->assertStatus(403);
        $this->assertNotContains("cheque", Payment::courierMethods());
        $this->assertNotContains("cheque", Payment::refundMethods());
    }

    public function test_cheque_payment_can_be_edited_while_in_hand(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $company = $this->customer("business");
        $order = $this->order($company, 900);
        $this->pay($admin, $order, 900, "cheque", ["transaction_ref" => "300", "cheque_bank" => "BCI"]);
        $payment = Payment::first();

        $this->actingAs($admin)->patch(route("payments.update", $payment), [
            "paid_at" => today()->toDateString(), "amount" => 800, "method" => "cheque", "transaction_ref" => "301", "cheque_bank" => "BCI",
        ])->assertSessionHasNoErrors();
        $this->assertSame("301", Cheque::first()->number);
        $this->assertEquals(800, (float) Cheque::first()->amount);

        // Switching to cash removes the cheque.
        $this->actingAs($admin)->patch(route("payments.update", $payment), [
            "paid_at" => today()->toDateString(), "amount" => 800, "method" => "cash",
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, Cheque::count());
        $this->assertNull($payment->fresh()->cheque_id);
    }

    // ---------- Bank statement ----------

    public function test_bank_statement_matches_transfers_and_deposits(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $customer = $this->customer();
        $o1 = $this->order($customer, 4500);
        $o2 = $this->order($customer, 1250);
        $this->pay($admin, $o1, 4500, "transfer", ["transaction_ref" => "FT77123"]);
        $this->pay($admin, $o2, 1250, "transfer", ["transaction_ref" => "XYZ"]); // reference absent from the statement
        $this->actingAs($admin)->post(route("treasury.deposits.store"), ["deposited_at" => today()->toDateString(), "cash_amount" => 8000]);

        $d = today()->format("d/m/Y");
        $csv = "Data;Descritivo;Crédito\n"
            . "$d;TRF DE JOAO FT77123 PAGAMENTO;4.500,00\n"
            . "$d;DEPOSITO NUMERARIO BALCAO;8.000,00\n"
            . "$d;TRF RECEBIDA;1.250,00\n"
            . "$d;JUROS CREDORES;12,30\n";

        $this->actingAs($admin)->post(route("wallet-reconciliation.store"), [
            "provider" => "transfer", "file" => UploadedFile::fake()->createWithContent("extracto.csv", $csv),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame("matched", WalletStatementLine::where("amount", 4500)->first()->status);
        $this->assertSame("matched", WalletStatementLine::where("amount", 8000)->first()->status);
        $this->assertSame("matched", WalletStatementLine::where("amount", 1250)->first()->status);
        $this->assertSame("unmatched", WalletStatementLine::where("amount", 12.3)->first()->status);
        $this->assertNotNull(BankDeposit::first()->confirmed_at);
        $this->assertSame(2, Payment::whereNotNull("reconciled_at")->count());

        // Re-importing the same statement adds nothing.
        $this->actingAs($admin)->post(route("wallet-reconciliation.store"), [
            "provider" => "transfer", "file" => UploadedFile::fake()->createWithContent("extracto.csv", $csv),
        ]);
        $this->assertSame(4, WalletStatementLine::count());
    }

    public function test_settings_and_payment_forms_render(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $this->actingAs($admin)->post(route("admin.business-settings.update"), [
            "vat_rate" => 16, "cheque_policy" => "all", "cash_till_limit" => 50000,
        ])->assertSessionHasNoErrors();
        $this->assertSame("all", Cheque::policy());
        $this->actingAs($admin)->get(route("admin.business-settings.index"))->assertOk()->assertSee(__("app.cheque_policy"));

        // With "all", individual customers see the cheque option.
        $order = $this->order($this->customer(), 600);
        $this->actingAs($admin)->get(route("orders.payment", $order))->assertOk()->assertSee('value="cheque"', false)->assertSee(__("app.tendered"));
    }
}
