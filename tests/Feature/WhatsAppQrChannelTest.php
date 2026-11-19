<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WebSessionGateway;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppWebService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Free QR-code connection for conversations; automatic notifications only on the official API. */
class WhatsAppQrChannelTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = "qr-test-token-123456";

    protected function setUp(): void
    {
        parent::setUp();
        config([
            "whatsapp.driver" => "web",
            "whatsapp.notifications_driver" => null,
            "whatsapp.web" => ["url" => "http://127.0.0.1:3010", "token" => self::TOKEN, "timeout" => 5, "autostart" => false],
            "whatsapp.cloud" => ["api_version" => "v21.0", "phone_number_id" => "123", "access_token" => "tok", "timeout" => 5],
        ]);
    }

    public function test_reply_goes_out_through_the_qr_service_without_24h_window(): void
    {
        Http::fake(["127.0.0.1:3010/send" => Http::response(["id" => "3EB0ABC"])]);
        $customer = Customer::create(["name" => "Maria", "phone" => "841234567", "address" => "Matola"]);
        // The customer last wrote a week ago: the official API would refuse free text.
        $this->inbound("258841234567", "olá", "web", $customer)->forceFill(["created_at" => now()->subWeek()])->save();

        $this->actingAs(User::factory()->cashier()->create())
            ->post("/messages/clients/258841234567/reply", ["body" => "Bom dia, Maria!"])
            ->assertRedirect("/messages/clients/258841234567");

        $out = WhatsAppMessage::where("direction", "out")->firstOrFail();
        $this->assertSame("web", $out->channel);
        $this->assertSame("sent", $out->status);
        $this->assertSame("3EB0ABC", $out->provider_message_id);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === "http://127.0.0.1:3010/send"
            && $r->hasHeader("Authorization", "Bearer " . self::TOKEN)
            && $r["to"] === "258841234567" && $r["text"] === "Bom dia, Maria!");
    }

    public function test_manual_template_is_sent_as_plain_text_over_qr(): void
    {
        Http::fake(["127.0.0.1:3010/send" => Http::response(["id" => "3EB0T"])]);
        $customer = Customer::create(["name" => "Hotel Sol", "phone" => "841234567", "address" => "Matola"]);
        $template = MessageTemplate::create([
            "name" => "Pronto", "body" => "Olá {customer}!", "wa_template_name" => "pedido_pronto", "wa_params" => ["customer"],
        ]);

        $this->actingAs(User::factory()->cashier()->create())
            ->post("/messages/clients/send-template", ["customer_id" => $customer->id, "template_id" => $template->id]);

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame("text", $message->type);
        $this->assertSame("Olá Hotel Sol!", $message->body);
        Http::assertSent(fn (HttpRequest $r) => $r["text"] === "Olá Hotel Sol!");
    }

    public function test_automatic_notifications_are_off_without_the_official_api(): void
    {
        Http::fake();
        $order = $this->readyNotificationSetup();

        $this->actingAs(User::factory()->create())->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id, "status" => "ready", "received_at" => $order->received_at->format("Y-m-d"),
        ]);

        $this->assertSame(0, WhatsAppMessage::count());
        Http::assertNothingSent();
    }

    public function test_automatic_notifications_use_the_official_api_alongside_qr(): void
    {
        config(["whatsapp.notifications_driver" => "cloud"]);
        Http::fake(["graph.facebook.com/*" => Http::response(["messages" => [["id" => "wamid.CLOUD1"]]])]);
        $order = $this->readyNotificationSetup();

        $this->actingAs(User::factory()->create())->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id, "status" => "ready", "received_at" => $order->received_at->format("Y-m-d"),
        ]);

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame("cloud", $message->channel);
        $this->assertSame("template", $message->type);
        $this->assertSame("wamid.CLOUD1", $message->provider_message_id);
        Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), "3010"));
    }

    public function test_reply_to_a_notification_answers_on_the_official_number(): void
    {
        config(["whatsapp.notifications_driver" => "cloud"]);
        Http::fake(["graph.facebook.com/*" => Http::response(["messages" => [["id" => "wamid.R1"]]])]);
        $customer = Customer::create(["name" => "Maria", "phone" => "841234567", "address" => "Matola"]);
        $this->inbound("258841234567", "Obrigada pelo aviso", "cloud", $customer);

        $this->actingAs(User::factory()->cashier()->create())
            ->post("/messages/clients/258841234567/reply", ["body" => "De nada!"]);

        $this->assertSame("cloud", WhatsAppMessage::where("direction", "out")->value("channel"));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), "graph.facebook.com"));
    }

    public function test_qr_webhook_stores_signed_messages_only(): void
    {
        $customer = Customer::create(["name" => "Maria", "phone" => "841234567", "address" => "Matola"]);
        $payload = ["object" => "whatsapp_web", "entry" => [["changes" => [["field" => "messages", "value" => [
            "contacts" => [["wa_id" => "258841234567", "profile" => ["name" => "Maria"]]],
            "messages" => [["from" => "258841234567", "id" => "3EB0IN", "type" => "text", "text" => ["body" => "Já está pronta?"]]],
        ]]]]]];

        $this->postJson("/webhooks/whatsapp-web", $payload, ["X-Signature-256" => "sha256=bad"])->assertStatus(401);
        $this->assertSame(0, WhatsAppMessage::count());

        $this->signedWebPost($payload)->assertOk();
        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame("web", $message->channel);
        $this->assertSame($customer->id, $message->customer_id);
        $this->assertSame("Já está pronta?", $message->body);
    }

    public function test_qr_webhook_updates_delivery_ticks(): void
    {
        $out = WhatsAppMessage::create([
            "channel" => "web", "phone" => "258841234567", "direction" => "out", "type" => "text",
            "body" => "oi", "status" => "sent", "provider_message_id" => "3EB0OUT",
        ]);

        $this->signedWebPost(["entry" => [["changes" => [["value" => ["statuses" => [["id" => "3EB0OUT", "status" => "read"]]]]]]]])->assertOk();

        $this->assertSame("read", $out->fresh()->status);
    }

    public function test_qr_webhook_is_closed_without_a_strong_token(): void
    {
        config(["whatsapp.web.token" => "short"]);
        $body = json_encode(["entry" => []]);

        $this->call("POST", "/webhooks/whatsapp-web", [], [], [], [
            "CONTENT_TYPE" => "application/json",
            "HTTP_X_SIGNATURE_256" => "sha256=" . hash_hmac("sha256", $body, "short"),
        ], $body)->assertStatus(401);
    }

    public function test_unlinked_phone_is_retryable_but_number_without_whatsapp_is_not(): void
    {
        $gateway = new WebSessionGateway(config("whatsapp.web"));

        Http::fake(["127.0.0.1:3010/send" => Http::sequence()
            ->push(["error" => "WhatsApp não está ligado"], 503)
            ->push(["error" => "Este número não tem WhatsApp."], 422)]);
        try {
            $gateway->sendText("258841234567", "oi");
            $this->fail("expected exception");
        } catch (WhatsAppException $e) {
            $this->assertTrue($e->retryable);
        }

        try {
            $gateway->sendText("258841234567", "oi");
            $this->fail("expected exception");
        } catch (WhatsAppException $e) {
            $this->assertFalse($e->retryable);
            $this->assertSame("Este número não tem WhatsApp.", $e->getMessage());
        }
    }

    public function test_settings_page_shows_the_qr_status_and_can_unlink(): void
    {
        Http::fake([
            "127.0.0.1:3010/status" => Http::response(["status" => "qr", "qr" => "data:image/png;base64,AAA", "me" => null]),
            "127.0.0.1:3010/logout" => Http::response(["ok" => true]),
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->get("/admin/notifications")->assertOk()
            ->assertSee(__("app.wa_channel_web"))
            ->assertSee(__("app.wa_notifications_off_help"));

        $this->actingAs($admin)->getJson("/admin/notifications/qr-status")
            ->assertOk()->assertJson(["status" => "qr", "qr" => "data:image/png;base64,AAA"]);

        $this->actingAs($admin)->post("/admin/notifications/qr-logout")->assertSessionHas("success");
        Http::assertSent(fn (HttpRequest $r) => $r->url() === "http://127.0.0.1:3010/logout");

        $this->actingAs(User::factory()->cashier()->create())->getJson("/admin/notifications/qr-status")->assertForbidden();
    }

    public function test_inbox_warns_when_the_phone_is_not_linked(): void
    {
        Http::fake(["127.0.0.1:3010/status" => Http::response(["status" => "qr", "qr" => null, "me" => null])]);

        $this->actingAs(User::factory()->cashier()->create())->get("/messages/clients")
            ->assertOk()->assertSee(__("app.wa_web_disconnected_inbox"));
    }

    public function test_admin_links_a_number_by_qr_without_editing_the_env(): void
    {
        @unlink(WhatsAppSettings::tokenPath());
        config(["whatsapp.driver" => "log", "whatsapp.web.token" => null]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/admin/notifications/channels", ["driver" => "web", "notifications_driver" => ""])
            ->assertRedirect("/admin/notifications")->assertSessionHas("success");

        // The shared secret is generated for the QR service, which reads the same file.
        $this->assertFileExists(WhatsAppSettings::tokenPath());
        $token = trim(file_get_contents(WhatsAppSettings::tokenPath()));
        $this->assertGreaterThanOrEqual(16, strlen($token));

        // On the next request the stored choice wins over the .env default.
        config(["whatsapp.driver" => "log", "whatsapp.web.token" => null]);
        WhatsAppSettings::apply();
        $this->assertSame("web", config("whatsapp.driver"));
        $this->assertSame($token, config("whatsapp.web.token"));

        // Notifications cannot go through the QR connection.
        $this->actingAs($admin)->post("/admin/notifications/channels", ["driver" => "web", "notifications_driver" => "web"])
            ->assertSessionHasErrors("notifications_driver");
        $this->actingAs(User::factory()->cashier()->create())
            ->post("/admin/notifications/channels", ["driver" => "cloud"])->assertForbidden();

        @unlink(WhatsAppSettings::tokenPath());
    }

    public function test_settings_offer_the_qr_connection_when_using_the_api(): void
    {
        config(["whatsapp.driver" => "cloud"]);

        $this->actingAs(User::factory()->create())->get("/admin/notifications")->assertOk()
            ->assertSee('name="driver" value="web"', false)
            ->assertSee(__("app.wa_driver_web_help"));
    }

    public function test_replies_typed_on_the_phone_stay_in_the_conversation(): void
    {
        $customer = Customer::create(["name" => "Maria", "phone" => "841234567", "address" => "Matola"]);
        $in = $this->inbound("258841234567", "Já está pronta?", "web", $customer);
        $echo = ["entry" => [["changes" => [["value" => ["echoes" => [
            ["from" => "258841234567", "id" => "3EB0PHONE", "type" => "text", "text" => ["body" => "Sim, pode levantar."]],
        ]]]]]]];

        $this->signedWebPost($echo)->assertOk();
        $this->signedWebPost($echo)->assertOk(); // delivered twice: stored once

        $out = WhatsAppMessage::where("direction", "out")->sole();
        $this->assertSame("Sim, pode levantar.", $out->body);
        $this->assertSame($customer->id, $out->customer_id);
        $this->assertTrue($out->isFromPhone());
        $this->assertNull($out->user_id);
        $this->assertNotNull($in->fresh()->read_by_staff_at, "answered on the phone = read");

        $this->actingAs(User::factory()->cashier()->create())->get("/messages/clients/258841234567")
            ->assertOk()->assertSee("Sim, pode levantar.")->assertSee(__("app.wa_from_phone"));
    }

    public function test_the_official_api_webhook_cannot_inject_phone_replies(): void
    {
        config(["whatsapp.cloud.app_secret" => null]); // testing env accepts unsigned Meta calls

        $this->postJson("/webhooks/whatsapp", ["entry" => [["changes" => [["value" => ["echoes" => [
            ["from" => "258841234567", "id" => "X1", "type" => "text", "text" => ["body" => "fake"]],
        ]]]]]]])->assertOk();

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_choosing_the_qr_code_starts_the_service_and_another_channel_stops_it(): void
    {
        config(["whatsapp.web.autostart" => true]);
        $service = $this->mock(WhatsAppWebService::class);
        $service->shouldReceive("enabled")->andReturn(true);
        $service->shouldReceive("start")->once()->andReturn("started");
        $service->shouldReceive("stop")->once();
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/admin/notifications/channels", ["driver" => "web"])
            ->assertSessionHas("success", fn ($m) => str_contains($m, __("app.wa_web_autostart_started")));
        $this->actingAs($admin)->post("/admin/notifications/channels", ["driver" => "cloud"])
            ->assertSessionHas("success");

        @unlink(WhatsAppSettings::tokenPath());
    }

    public function test_service_is_left_alone_when_autostart_is_off(): void
    {
        config(["whatsapp.web.autostart" => false]);
        $service = $this->mock(WhatsAppWebService::class);
        $service->shouldReceive("enabled")->andReturn(false);
        $service->shouldNotReceive("start");
        $service->shouldNotReceive("stop");

        $this->actingAs(User::factory()->create())->post("/admin/notifications/channels", ["driver" => "web"])
            ->assertSessionHas("success");

        $this->artisan("whatsapp:web-service")->assertSuccessful();
    }

    public function test_start_button_and_booting_state(): void
    {
        Http::fake(["127.0.0.1:3010/status" => fn () => throw new ConnectionException("down")]);
        $service = $this->mock(WhatsAppWebService::class);
        $service->shouldReceive("isBooting")->andReturn(true, true, false, false);
        $service->shouldReceive("logTail")->andReturn("npm install…");
        $service->shouldReceive("start")->once()->andReturn("failed");
        $admin = User::factory()->create();

        $this->actingAs($admin)->getJson("/admin/notifications/qr-status")
            ->assertJson(["status" => "booting", "log" => "npm install…", "can_start" => false]);
        $this->actingAs($admin)->getJson("/admin/notifications/qr-status")
            ->assertJson(["status" => "offline", "can_start" => true]);

        $this->actingAs($admin)->post("/admin/notifications/qr-start")
            ->assertSessionHas("error", __("app.wa_web_autostart_failed"));
        $this->actingAs(User::factory()->cashier()->create())->post("/admin/notifications/qr-start")->assertForbidden();
    }

    public function test_scheduler_restarts_the_service_only_for_the_qr_channel(): void
    {
        $service = $this->mock(WhatsAppWebService::class);
        $service->shouldReceive("enabled")->andReturn(true);
        $service->shouldReceive("start")->once()->andReturn("started");

        $this->artisan("whatsapp:web-service")->assertSuccessful();

        config(["whatsapp.driver" => "cloud"]);
        $this->artisan("whatsapp:web-service")->assertSuccessful(); // start() not called again
    }

    private function readyNotificationSetup(): Order
    {
        $template = MessageTemplate::create([
            "name" => "Pronta", "body" => "Pronta {order}", "wa_template_name" => "pedido_pronto", "wa_language" => "pt_PT", "wa_params" => ["order"],
        ]);
        SiteSetting::set("notify_order_ready_enabled", "1");
        SiteSetting::set("notify_order_ready_template_id", $template->id);
        $customer = Customer::create(["name" => "Ana", "phone" => "841234567", "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORDQR1", "customer_id" => $customer->id, "status" => "ironing",
            "received_at" => now()->toDateString(), "total_price" => 500,
        ]);
    }

    private function inbound(string $phone, string $body, string $channel, ?Customer $customer = null): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            "channel" => $channel, "phone" => $phone, "customer_id" => $customer?->id, "direction" => "in",
            "type" => "text", "body" => $body, "status" => "received", "provider_message_id" => "in." . uniqid(),
        ]);
    }

    private function signedWebPost(array $payload)
    {
        $body = json_encode($payload);

        return $this->call("POST", "/webhooks/whatsapp-web", [], [], [], [
            "CONTENT_TYPE" => "application/json",
            "HTTP_X_SIGNATURE_256" => "sha256=" . hash_hmac("sha256", $body, self::TOKEN),
        ], $body);
    }
}
