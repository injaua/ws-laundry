<?php

namespace Tests\Feature;

use App\Jobs\ProcessMobileTransaction;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\MessageTemplate;
use App\Models\MobileTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WalletStatement;
use App\Models\WalletStatementLine;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\MobileMoney\MobilePayments;
use App\Services\WhatsApp\Messenger;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MobilePaymentsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function order(float $total = 1500, string $phone = "841234567"): Order
    {
        $customer = Customer::create(["name" => "Cliente Móvel " . ++$this->seq, "phone" => "+258" . $phone, "address" => "Maputo"]);

        return Order::create([
            "order_number" => "ORD-MM-" . $this->seq,
            "customer_id" => $customer->id,
            "status" => "ready",
            "received_at" => now()->toDateString(),
            "total_price" => $total,
        ]);
    }

    private function simulate(string $provider = "mpesa"): void
    {
        SiteSetting::set("mm_{$provider}_mode", "simulation");
    }

    /** Sends the payment link with a messenger that fails with $error. */
    private function sendLinkFailingWith(\Throwable $error)
    {
        $this->simulate();
        MessageTemplate::create(["name" => "Pedido de pagamento", "body" => "Pague {amount}: {pay_link}", "is_active" => true]);
        $this->mock(Messenger::class)->shouldReceive("sendTemplate")->andThrow($error);
        $order = $this->order(1500);

        return $this->actingAs(User::factory()->create(["role" => "admin"]))
            ->from("/orders/{$order->id}")->post(route("mobile-payments.send-link", $order));
    }

    public function test_send_link_shows_whatsapp_errors_meant_for_the_user(): void
    {
        $this->sendLinkFailingWith(new WhatsAppException(__("app.customer_without_phone")))
            ->assertSessionHas("error", __("app.customer_without_phone"));
    }

    public function test_send_link_hides_technical_errors(): void
    {
        $this->sendLinkFailingWith(new \RuntimeException("SQLSTATE[HY000]: General error (SQL: insert into `whatsapp_messages` …)"))
            ->assertSessionHas("error", __("app.mm_link_failed"));
        $this->assertStringNotContainsString("SQLSTATE", session("error"));
    }

    // ---------- Phase 1: manual reference ----------

    public function test_mobile_payment_requires_a_unique_reference(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $a = $this->order(1000);
        $b = $this->order(1000, "841234568");

        $this->actingAs($admin)->post("/payments", ["order_id" => $a->id, "paid_at" => now()->toDateString(), "amount" => 500, "method" => "mpesa"])
            ->assertSessionHasErrors("transaction_ref");

        $this->actingAs($admin)->post("/payments", ["order_id" => $a->id, "paid_at" => now()->toDateString(), "amount" => 500, "method" => "mpesa", "transaction_ref" => "abc 123x", "payer_phone" => "841234567"])
            ->assertSessionHasNoErrors();
        $this->assertSame("ABC123X", Payment::first()->transaction_ref);

        // Same SMS used again on another order: refused, whatever the spacing or case.
        $this->actingAs($admin)->post("/payments", ["order_id" => $b->id, "paid_at" => now()->toDateString(), "amount" => 500, "method" => "mpesa", "transaction_ref" => "ABC123X"])
            ->assertSessionHasErrors("transaction_ref");
        // Cash needs no reference.
        $this->actingAs($admin)->post("/payments", ["order_id" => $b->id, "paid_at" => now()->toDateString(), "amount" => 500, "method" => "cash"])
            ->assertSessionHasNoErrors();
    }

    public function test_each_wallet_posts_to_its_own_account(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $order = $this->order(1000);

        $this->actingAs($admin)->post("/payments", ["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 1000, "method" => "emola", "transaction_ref" => "EM1000"])
            ->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "payment");
        $this->assertNotNull($entry);
        $this->assertTrue($entry->lines()->whereHas("account", fn ($q) => $q->where("code", "1.2.3"))->where("debit", ">", 0)->exists());
    }

    // ---------- Phase 2: push requests ----------

    public function test_counter_push_in_simulation_records_the_payment(): void
    {
        $this->simulate();
        $cashier = User::factory()->create(["role" => "admin"]);
        $order = $this->order(1500);

        $this->actingAs($cashier)->post(route("mobile-payments.store"), ["order_id" => $order->id, "provider" => "mpesa", "phone" => "84 123 4567"])
            ->assertRedirect();

        $tx = MobileTransaction::firstOrFail();
        app(MobilePayments::class)->process($tx->fresh()); // idempotent if the after-response job already ran
        $tx->refresh();

        $this->assertSame("success", $tx->status);
        $this->assertSame("258841234567", $tx->phone);
        $payment = $tx->payment;
        $this->assertNotNull($payment);
        $this->assertSame("mpesa", $payment->method);
        $this->assertEquals(1500, (float) $payment->amount);
        $this->assertNotNull($payment->transaction_ref);
        $this->assertSame(1, Payment::count());
        // Fully paid and ready: goes out for delivery.
        $this->assertSame("delivering", $order->fresh()->status);

        $this->actingAs($cashier)->getJson(route("mobile-payments.status", $tx))->assertOk()->assertJson(["status" => "success", "open" => false]);
        $this->actingAs($cashier)->get(route("mobile-payments.show", $tx))->assertOk()->assertSee($tx->reference);
        $this->actingAs($cashier)->get(route("mobile-payments.index"))->assertOk();
    }

    public function test_push_goes_out_after_the_response_unless_the_queue_is_chosen(): void
    {
        $this->simulate();
        $admin = User::factory()->create(["role" => "admin"]);
        config(["queue.default" => "database"]);

        // Default: right after the response (works with a once-a-minute worker too).
        Bus::fake();
        $this->actingAs($admin)->post(route("mobile-payments.store"), ["order_id" => $this->order()->id, "provider" => "mpesa", "phone" => "841234567"]);
        Bus::assertDispatchedAfterResponse(ProcessMobileTransaction::class);

        // MOBILE_MONEY_QUEUE=true (permanent worker): through the queue.
        config(["mobile_money.queue" => true]);
        Bus::fake();
        $this->actingAs($admin)->post(route("mobile-payments.store"), ["order_id" => $this->order(1500, "841234569")->id, "provider" => "mpesa", "phone" => "841234569"]);
        Bus::assertDispatched(ProcessMobileTransaction::class);
        Bus::assertNotDispatchedAfterResponse(ProcessMobileTransaction::class);
    }

    public function test_wrong_network_and_failed_and_expired_requests(): void
    {
        $this->simulate();
        $admin = User::factory()->create(["role" => "admin"]);
        $order = $this->order(800);
        $service = app(MobilePayments::class);

        // e-Mola number for an M-Pesa request
        $this->actingAs($admin)->post(route("mobile-payments.store"), ["order_id" => $order->id, "provider" => "mpesa", "phone" => "861234567"])
            ->assertSessionHasErrors();

        // No balance → failed, nothing recorded
        $failed = $service->process($service->request($order, "mpesa", "840000000", null, $admin));
        $this->assertSame("failed", $failed->fresh()->status);
        $this->assertSame(0, Payment::count());

        // No answer → stays open, then expires
        $open = $service->process($service->request($order, "mpesa", "840001111", null, $admin));
        $this->assertTrue($open->fresh()->isOpen());
        // Only one open request per order
        $this->expectsOpenRequestRefused($service, $order, $admin);

        $this->travel(MobileTransaction::EXPIRE_MINUTES + 1)->minutes();
        $this->artisan("mobile-money:refresh")->assertSuccessful();
        $this->assertSame("expired", $open->fresh()->status);
        $this->assertSame(0, Payment::count());
    }

    private function expectsOpenRequestRefused(MobilePayments $service, Order $order, User $user): void
    {
        try {
            $service->request($order, "mpesa", "841234567", null, $user);
            $this->fail("A second open request should be refused.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey("order_id", $e->errors() + ["order_id" => true]);
        }
    }

    public function test_courier_can_only_charge_own_delivery(): void
    {
        $this->simulate();
        $courier = User::factory()->create(["role" => "courier"]);
        $order = $this->order(600);

        // No job of theirs for this order
        $this->actingAs($courier)->post(route("mobile-payments.store"), ["order_id" => $order->id, "provider" => "mpesa", "phone" => "841234567"])
            ->assertStatus(403);
        $this->actingAs($courier)->get(route("mobile-payments.index"))->assertStatus(403);
    }

    public function test_mpesa_gateway_talks_to_the_openapi(): void
    {
        $key = @openssl_pkey_new(["private_key_bits" => 1024, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            $this->markTestSkipped("OpenSSL cannot generate keys here.");
        }
        $public = openssl_pkey_get_details($key)["key"];

        SiteSetting::set("mm_mpesa_mode", "sandbox");
        MobileMoneySettings::save("mpesa", ["mode" => "sandbox", "api_key" => "test-key", "public_key" => $public, "service_provider_code" => "171717", "origin" => "developer.mpesa.vm.co.mz"]);
        $this->assertTrue(MobileMoneySettings::configured("mpesa"));

        Http::fake([
            "api.sandbox.vm.co.mz:18352/*" => Http::response(["output_ResponseCode" => "INS-0", "output_ResponseDesc" => "Request processed successfully", "output_TransactionID" => "8BCX12ZZ", "output_ConversationID" => "conv-1"], 201),
        ]);

        $admin = User::factory()->create(["role" => "admin"]);
        $service = app(MobilePayments::class);
        $tx = $service->process($service->request($this->order(700), "mpesa", "841234567", null, $admin))->fresh();

        $this->assertSame("success", $tx->status);
        $this->assertSame("8BCX12ZZ", $tx->provider_ref);
        $this->assertSame("8BCX12ZZ", $tx->payment->transaction_ref);
        Http::assertSent(fn ($request) => str_starts_with($request->header("Authorization")[0], "Bearer ")
            && $request["input_ServiceProviderCode"] === "171717"
            && $request["input_Amount"] === "700"
            && $request["input_CustomerMSISDN"] === "258841234567");
    }

    public function test_mpesa_insufficient_balance_fails(): void
    {
        $key = @openssl_pkey_new(["private_key_bits" => 1024, "private_key_type" => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            $this->markTestSkipped("OpenSSL cannot generate keys here.");
        }
        MobileMoneySettings::save("mpesa", ["mode" => "sandbox", "api_key" => "k", "public_key" => openssl_pkey_get_details($key)["key"], "service_provider_code" => "171717"]);
        Http::fake(["*" => Http::response(["output_ResponseCode" => "INS-2006", "output_ResponseDesc" => "Insufficient balance"], 422)]);

        $service = app(MobilePayments::class);
        $tx = $service->process($service->request($this->order(700), "mpesa", "841234567", null, User::factory()->create()))->fresh();

        $this->assertSame("failed", $tx->status);
        $this->assertSame("INS-2006", $tx->response_code);
        $this->assertSame(0, Payment::count());
    }

    // ---------- Reconciliation ----------

    public function test_statement_import_matches_references(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $order = $this->order(3000);
        foreach ([["AAA111", 1000], ["BBB222", 1000], ["CCC333", 500]] as [$ref, $amount]) {
            Payment::create(["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => $amount, "method" => "mpesa", "transaction_ref" => $ref]);
        }

        $csv = "Data;ID da Transacao;Valor;Telefone\n"
            . now()->format("d/m/Y") . " 10:00;AAA111;1000,00;841234567\n"
            . now()->format("d/m/Y") . " 11:00;BBB222;900,00;841234567\n"
            . now()->format("d/m/Y") . " 12:00;ZZZ999;250,00;849999999\n";
        $file = UploadedFile::fake()->createWithContent("extracto.csv", $csv);

        $this->actingAs($admin)->post(route("wallet-reconciliation.store"), ["provider" => "mpesa", "file" => $file])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame("matched", WalletStatementLine::firstWhere("transaction_ref", "AAA111")->status);
        $this->assertSame("amount_mismatch", WalletStatementLine::firstWhere("transaction_ref", "BBB222")->status);
        $this->assertSame("unmatched", WalletStatementLine::firstWhere("transaction_ref", "ZZZ999")->status);
        $this->assertNotNull(Payment::firstWhere("transaction_ref", "AAA111")->reconciled_at);
        $this->assertNull(Payment::firstWhere("transaction_ref", "CCC333")->reconciled_at);

        $statement = WalletStatement::first();
        $this->actingAs($admin)->get(route("wallet-reconciliation.show", $statement))
            ->assertOk()->assertSee("CCC333"); // recorded but missing from the statement
        $this->actingAs($admin)->get(route("wallet-reconciliation.index"))->assertOk();

        // Importing the same file again adds nothing.
        $again = UploadedFile::fake()->createWithContent("extracto.csv", $csv);
        $this->actingAs($admin)->post(route("wallet-reconciliation.store"), ["provider" => "mpesa", "file" => $again]);
        $this->assertSame(3, WalletStatementLine::count());
    }

    public function test_unknown_columns_ask_for_mapping(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $file = UploadedFile::fake()->createWithContent("x.csv", "Coluna A;Coluna B\nREF1;100\n");

        $response = $this->actingAs($admin)->post(route("wallet-reconciliation.store"), ["provider" => "emola", "file" => $file]);
        $response->assertRedirect();
        $this->assertStringContainsString("/wallet-reconciliation/mapping/", $response->headers->get("Location"));
        $this->actingAs($admin)->get($response->headers->get("Location"))->assertOk()->assertSee("Coluna A");

        $token = basename($response->headers->get("Location"));
        $this->actingAs($admin)->post(route("wallet-reconciliation.map", $token), ["columns" => ["ref" => 0, "amount" => 1]])
            ->assertRedirect();
        $this->assertSame("unmatched", WalletStatementLine::firstWhere("transaction_ref", "REF1")->status);
    }

    public function test_cashier_cannot_reconcile_or_configure(): void
    {
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->actingAs($cashier)->get(route("wallet-reconciliation.index"))->assertStatus(403);
        $this->actingAs($cashier)->get(route("admin.mobile-money.edit"))->assertStatus(403);
    }

    // ---------- Settings ----------

    public function test_settings_store_secrets_encrypted_and_keep_them_when_blank(): void
    {
        $admin = User::factory()->create(["role" => "admin"]);
        $this->actingAs($admin)->get(route("admin.mobile-money.edit"))->assertOk();

        $this->actingAs($admin)->put(route("admin.mobile-money.update", "mpesa"), ["mode" => "sandbox", "api_key" => "secret-api", "public_key" => "PUBKEY", "service_provider_code" => "171717"])
            ->assertRedirect();
        $this->assertNotSame("secret-api", SiteSetting::get("mm_mpesa_api_key"));
        $this->assertSame("secret-api", MobileMoneySettings::get("mpesa")["api_key"]);

        $this->actingAs($admin)->put(route("admin.mobile-money.update", "mpesa"), ["mode" => "live", "api_key" => "", "public_key" => "", "service_provider_code" => "171717"]);
        $this->assertSame("secret-api", MobileMoneySettings::get("mpesa")["api_key"]);
        $this->assertSame("live", MobileMoneySettings::mode("mpesa"));

        // Secrets never go back to the browser
        $this->actingAs($admin)->get(route("admin.mobile-money.edit"))->assertOk()->assertDontSee("secret-api");
    }

    // ---------- Phase 3: public link ----------

    public function test_customer_pays_from_signed_link(): void
    {
        $this->simulate();
        $order = $this->order(900);

        $this->get(route("pay.show", $order))->assertStatus(403); // unsigned
        $this->get($order->payLink())->assertOk()->assertSee("900");

        $requestUrl = URL::temporarySignedRoute("pay.request", now()->addHour(), ["order" => $order->id]);
        $this->post($requestUrl, ["provider" => "mpesa", "phone" => "841234567"])->assertRedirect();

        $tx = MobileTransaction::firstOrFail();
        $this->assertSame("link", $tx->channel);
        $this->assertNull($tx->user_id);
        app(MobilePayments::class)->process($tx->fresh());
        $this->assertSame("success", $tx->fresh()->status);
        $this->assertEquals(900, (float) $order->payments()->sum("amount"));

        $this->get(URL::temporarySignedRoute("pay.status-page", now()->addHour(), ["transaction" => $tx->reference]))->assertOk();
        $this->getJson(URL::temporarySignedRoute("pay.status", now()->addHour(), ["transaction" => $tx->reference]))->assertJson(["status" => "success"]);
    }

    public function test_lookup_requires_matching_phone(): void
    {
        $this->simulate();
        $order = $this->order(500, "841110000");

        $this->get(route("pay.lookup"))->assertOk();
        $this->post(route("pay.lookup.submit"), ["order_number" => $order->order_number, "phone" => "849999999"])
            ->assertSessionHasErrors("order_number");
        $this->post(route("pay.lookup.submit"), ["order_number" => $order->order_number, "phone" => "84 111 0000"])
            ->assertRedirectContains("/pagar/encomenda/" . $order->id);
    }

    public function test_public_pay_is_hidden_when_no_wallet_is_enabled(): void
    {
        $this->get(route("pay.lookup"))->assertNotFound();
    }

    // ---------- Refund (B2C) ----------

    public function test_order_page_shows_charge_card_in_simulation(): void
    {
        $this->simulate();
        $admin = User::factory()->create(["role" => "admin"]);
        $order = $this->order(400);

        $this->actingAs($admin)->get(route("orders.show", $order))->assertOk()->assertSee(__("app.mm_charge_title"));
        $this->actingAs($admin)->get(route("orders.payment", $order))->assertOk()->assertSee(__("app.mm_send_request"));
    }
}
