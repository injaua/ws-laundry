<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\Payments\PaymentMethods;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Services\WhatsApp\WhatsAppChannels;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * WhatsApp and payments: company configuration by default, or a store's own configuration.
 */
class StoreOwnSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = "qr-test-token-123456";

    private Store $main;

    private Store $second;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            "whatsapp.driver" => "log",
            "whatsapp.notifications_driver" => null,
            "whatsapp.web" => ["url" => "http://127.0.0.1:3010", "token" => self::TOKEN, "timeout" => 5, "autostart" => false],
            "whatsapp.cloud" => ["api_version" => "v21.0", "phone_number_id" => "111", "access_token" => "company-token", "app_secret" => "company-secret", "verify_token" => "company-verify", "timeout" => 5],
        ]);

        $this->main = Store::orderBy("id")->first();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin)->post(route("admin.stores.store"), [
            "name" => "Loja da Matola", "code" => "lj02", "is_active" => 1, "copy_from" => $this->main->id,
        ])->assertRedirect();
        $this->second = Store::where("code", "LJ02")->firstOrFail();
        $this->post(route("admin.stores.switch", $this->second));
    }

    // ---------- Payments ----------

    public function test_a_store_gets_its_own_payment_methods_and_mobile_money_without_touching_the_company(): void
    {
        MobileMoneySettings::save("mpesa", ["mode" => "live", "api_key" => "company-key", "public_key" => "PUB", "service_provider_code" => "100000"]);

        // The page shows the company configuration until the store has its own.
        $this->get(route("admin.mobile-money.edit"))->assertOk()->assertSee(__("app.settings_level_store_inherits", ["name" => "Loja da Matola"]));

        $this->put(route("admin.mobile-money.methods"), ["level" => "store", "methods" => ["cash", "mpesa"]])->assertRedirect();
        $this->put(route("admin.mobile-money.update", "mpesa"), ["level" => "store", "mode" => "sandbox", "service_provider_code" => "200000"])->assertRedirect();

        $this->assertTrue(StoreSettings::owns("payments", $this->second->id));
        CurrentStore::run($this->second, function () {
            $this->assertSame(["cash", "mpesa"], PaymentMethods::enabled());
            $this->assertSame("sandbox", MobileMoneySettings::mode("mpesa"));
            $mpesa = MobileMoneySettings::get("mpesa");
            $this->assertSame("200000", $mpesa["service_provider_code"]);
            $this->assertSame("company-key", $mpesa["api_key"]); // copied from the company as a starting point
        });
        CurrentStore::run($this->main, function () {
            $this->assertSame(Payment::METHODS, PaymentMethods::enabled());
            $this->assertSame("live", MobileMoneySettings::mode("mpesa"));
            $this->assertSame("100000", MobileMoneySettings::get("mpesa")["service_provider_code"]);
        });

        // Changing the company afterwards does not reach the store with its own configuration.
        $this->put(route("admin.mobile-money.methods"), ["level" => "company", "methods" => ["cash", "transfer"]])->assertRedirect();
        $this->assertSame(["cash", "mpesa"], CurrentStore::run($this->second, fn () => PaymentMethods::enabled()));
        $this->assertSame(["cash", "transfer"], CurrentStore::run($this->main, fn () => PaymentMethods::enabled()));

        // Back to the company configuration.
        $this->post(route("admin.mobile-money.release"))->assertRedirect();
        $this->assertFalse(StoreSettings::owns("payments", $this->second->id));
        $this->assertSame(["cash", "transfer"], CurrentStore::run($this->second, fn () => PaymentMethods::enabled()));
        $this->assertSame(0, SiteSetting::where("store_id", $this->second->id)->where("key", "like", "mm_%")->count());
    }

    public function test_payments_only_accept_the_methods_of_the_store(): void
    {
        PaymentMethods::save(["cash", "transfer"], $this->second->id);
        $cashier = User::factory()->cashier()->create(["store_id" => $this->second->id]);
        $this->openTill($cashier);
        $order = CurrentStore::run($this->second, fn () => Order::create([
            "order_number" => "ORDPM1", "customer_id" => Customer::create(["name" => "Ana", "phone" => "841234567", "address" => "Matola"])->id,
            "status" => "ready", "received_at" => now()->toDateString(), "total_price" => 500,
        ]));

        $this->actingAs($cashier)->get(route("orders.payment", $order))->assertOk()
            ->assertSee('value="transfer"', false)->assertDontSee('<option value="mpesa"', false);
        $this->actingAs($cashier)->post(route("payments.store"), [
            "order_id" => $order->id, "paid_at" => today()->toDateString(), "amount" => 100, "method" => "mpesa", "transaction_ref" => "ABC123XYZ",
        ])->assertSessionHasErrors("method");

        // M-Pesa push requests need the M-Pesa method too.
        SiteSetting::set("mm_mpesa_mode", "simulation");
        $this->assertSame([], CurrentStore::run($this->second, fn () => MobileMoneySettings::enabledProviders()));
        $this->assertSame(["mpesa"], CurrentStore::run($this->main, fn () => MobileMoneySettings::enabledProviders()));
    }

    public function test_at_least_one_method_stays_accepted(): void
    {
        $this->put(route("admin.mobile-money.methods"), ["level" => "store", "methods" => []])->assertSessionHasErrors("methods");
        $this->assertFalse(StoreSettings::owns("payments", $this->second->id));
    }

    // ---------- WhatsApp ----------

    public function test_a_store_chats_through_its_own_qr_number(): void
    {
        $this->post(route("admin.notifications.channels"), ["level" => "store", "driver" => "web", "notifications_driver" => "off"])->assertRedirect();

        $this->assertTrue(StoreSettings::owns("whatsapp", $this->second->id));
        $this->assertSame("log", config("whatsapp.driver")); // the company is unchanged
        $this->assertSame("web", CurrentStore::run($this->second, fn () => app(WhatsAppGateway::class)->channel()));
        $this->assertSame("log", CurrentStore::run($this->main, fn () => app(WhatsAppGateway::class)->channel()));
        $this->assertNull(CurrentStore::run($this->second, fn () => app(WhatsAppChannels::class)->notificationsDriver()));

        // Its messages leave through its own session of the QR service.
        Http::fake(["127.0.0.1:3010/send" => Http::response(["id" => "3EBSTORE"])]);
        $cashier = User::factory()->cashier()->create(["store_id" => $this->second->id]);
        $this->actingAs($cashier)->post("/messages/clients/258841234567/reply", ["body" => "Olá"])->assertRedirect();
        Http::assertSent(fn (HttpRequest $r) => $r->url() === "http://127.0.0.1:3010/send"
            && $r->hasHeader("X-WA-Session", "store-{$this->second->id}"));

        // And customers writing to that number reach this store only.
        $payload = ["session" => "store-{$this->second->id}", "entry" => [["changes" => [["value" => ["messages" => [
            ["from" => "258849999999", "id" => "3EBIN1", "type" => "text", "text" => ["body" => "Bom dia"]],
        ]]]]]]];
        $body = json_encode($payload);
        $this->call("POST", "/webhooks/whatsapp-web", [], [], [], [
            "CONTENT_TYPE" => "application/json", "HTTP_X_SIGNATURE_256" => "sha256=" . hash_hmac("sha256", $body, self::TOKEN),
        ], $body)->assertOk();
        $this->assertSame($this->second->id, WhatsAppMessage::acrossStores()->where("provider_message_id", "3EBIN1")->value("store_id"));
    }

    public function test_messages_to_a_store_official_number_go_to_that_store(): void
    {
        WhatsAppSettings::save("cloud", "", $this->second->id, ["phone_number_id" => "222", "access_token" => "store-token", "app_secret" => "store-secret", "verify_token" => "store-verify"]);

        $config = WhatsAppSettings::for($this->second->id);
        $this->assertSame("222", $config["cloud"]["phone_number_id"]);
        $this->assertSame("store-token", $config["cloud"]["access_token"]);
        $this->assertSame("111", WhatsAppSettings::for($this->main->id)["cloud"]["phone_number_id"]);
        $this->assertNotSame("store-token", SiteSetting::where("store_id", $this->second->id)->where("key", "whatsapp_cloud_access_token")->value("value"));

        // Both Meta apps can register the webhook and sign calls.
        $this->get("/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=store-verify&hub.challenge=7")->assertOk()->assertSee("7");
        $this->get("/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=company-verify&hub.challenge=8")->assertOk()->assertSee("8");

        foreach (["222" => ["store-secret", "wamid.S"], "111" => ["company-secret", "wamid.C"]] as $phoneNumberId => [$secret, $id]) {
            $body = json_encode(["entry" => [["changes" => [["value" => [
                "metadata" => ["phone_number_id" => (string) $phoneNumberId],
                "messages" => [["from" => "258841111111", "id" => $id, "type" => "text", "text" => ["body" => "olá"]]],
            ]]]]]]);
            $this->call("POST", "/webhooks/whatsapp", [], [], [], [
                "CONTENT_TYPE" => "application/json", "HTTP_X_HUB_SIGNATURE_256" => "sha256=" . hash_hmac("sha256", $body, $secret),
            ], $body)->assertOk();
        }

        $this->assertSame($this->second->id, WhatsAppMessage::acrossStores()->where("provider_message_id", "wamid.S")->value("store_id"));
        // The company number only serves the stores without their own WhatsApp.
        $this->assertSame($this->main->id, WhatsAppMessage::acrossStores()->where("provider_message_id", "wamid.C")->value("store_id"));
    }

    public function test_releasing_whatsapp_brings_the_store_back_to_the_company_channel(): void
    {
        $this->post(route("admin.notifications.channels"), ["level" => "store", "driver" => "cloud"])->assertRedirect();
        $this->get(route("admin.notifications.index"))->assertOk()->assertSee(__("app.settings_level_release"));

        $this->post(route("admin.notifications.channels.release"))->assertRedirect();
        $this->assertFalse(StoreSettings::owns("whatsapp", $this->second->id));
        $this->assertSame("log", CurrentStore::run($this->second, fn () => app(WhatsAppGateway::class)->channel()));
        $this->actingAs(User::factory()->owner()->create(["store_id" => $this->second->id]))
            ->post(route("admin.notifications.channels.release"))->assertForbidden();
    }

    // ---------- Automatic notifications ----------

    public function test_a_new_store_uses_its_own_copy_of_the_notification_templates(): void
    {
        // Chosen at company level before the stores existed: points at the main store's template.
        $template = CurrentStore::run($this->main, fn () => MessageTemplate::create(["name" => "Pronta X", "body" => "Pronta {order}"]));
        // The second store has the same template (copied when it was created).
        CurrentStore::run($this->second, fn () => MessageTemplate::create(["name" => "Pronta X", "body" => "Pronta {order}"]));
        SiteSetting::setAt(null, "notify_order_ready_enabled", "1");
        SiteSetting::setAt(null, "notify_order_ready_template_id", $template->id);

        $this->post(route("admin.stores.store"), ["name" => "Loja 3", "code" => "lj03", "is_active" => 1, "copy_from" => $this->main->id])->assertRedirect();
        $third = Store::where("code", "LJ03")->firstOrFail();

        foreach ([$this->second, $third] as $store) {
            $chosen = CurrentStore::run($store, fn () => app(AutomaticNotifications::class)->template("order_ready"));
            $this->assertNotNull($chosen, "store {$store->code}");
            $this->assertSame($store->id, $chosen->store_id);
            $this->assertSame("Pronta X", $chosen->name);
        }
        // The new store got its own settings, pointing at its own copy.
        $this->assertSame(
            CurrentStore::run($third, fn () => MessageTemplate::where("name", "Pronta X")->value("id")),
            (int) SiteSetting::where("store_id", $third->id)->where("key", "notify_order_ready_template_id")->value("value"),
        );
    }
}
