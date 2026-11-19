<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The cashier's WhatsApp inbox: conversations happen inside the platform. */
class CustomerWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_cashiers_access_the_inbox(): void
    {
        $this->actingAs(User::factory()->cashier()->create())->get("/messages/clients")->assertOk();
        $this->actingAs(User::factory()->create())->get("/messages/clients")->assertForbidden();
        $this->actingAs(User::factory()->owner()->create())->get("/messages/clients")->assertForbidden();
    }

    public function test_search_lists_customers_with_phone(): void
    {
        $cashier = User::factory()->cashier()->create();
        Customer::create(["name" => "Hotel Com Telefone", "phone" => "+258841234567", "address" => "Matola"]);
        Customer::create(["name" => "Hotel Sem Telefone", "phone" => "", "address" => "Matola"]);

        $this->actingAs($cashier)->get("/messages/clients?search=Hotel")
            ->assertOk()
            ->assertSee("Hotel Com Telefone")
            ->assertDontSee("Hotel Sem Telefone");
    }

    public function test_thread_shows_messages_and_marks_inbound_as_read(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Pensão Local", "phone" => "84 123 4567", "address" => "Matola"]);
        $in = $this->inbound("258841234567", "Bom dia, a roupa já está pronta?", $customer);

        $this->actingAs($cashier)->get("/messages/clients/258841234567")
            ->assertOk()
            ->assertSee("Pensão Local")
            ->assertSee("Bom dia, a roupa já está pronta?");

        $this->assertNotNull($in->fresh()->read_by_staff_at);
    }

    public function test_cashier_replies_inside_the_platform(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Pensão Local", "phone" => "841234567", "address" => "Matola"]);
        $this->inbound("258841234567", "Olá", $customer);

        $this->actingAs($cashier)->post("/messages/clients/258841234567/reply", ["body" => "Sim, já está pronta!"])
            ->assertRedirect("/messages/clients/258841234567");

        $out = WhatsAppMessage::where("direction", "out")->firstOrFail();
        $this->assertSame("Sim, já está pronta!", $out->body);
        $this->assertSame($cashier->id, $out->user_id);
        $this->assertSame("sent", $out->status); // log driver, sync queue
        $this->assertStringStartsWith("log-", $out->provider_message_id);
    }

    public function test_free_text_is_blocked_outside_the_24h_window_when_live(): void
    {
        $this->useGateway(live: true);
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Antigo", "phone" => "841234567", "address" => "Matola"]);
        $this->inbound("258841234567", "Olá", $customer)->forceFill(["created_at" => now()->subHours(25)])->save();

        $this->actingAs($cashier)->post("/messages/clients/258841234567/reply", ["body" => "Olá de novo"])
            ->assertSessionHas("error", __("app.wa_window_closed"));

        $this->assertSame(0, WhatsAppMessage::where("direction", "out")->count());
    }

    public function test_free_text_is_allowed_inside_the_window_when_live(): void
    {
        $gateway = $this->useGateway(live: true);
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Recente", "phone" => "841234567", "address" => "Matola"]);
        $this->inbound("258841234567", "Olá", $customer);

        $this->actingAs($cashier)->post("/messages/clients/258841234567/reply", ["body" => "Bom dia!"]);

        $this->assertSame([["text", "258841234567", "Bom dia!"]], $gateway->sent);
    }

    public function test_template_with_order_context_is_rendered_and_sent(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Residencial", "phone" => "+258860000000", "address" => "Matola"]);
        $template = MessageTemplate::create(["name" => "Pronto", "body" => "Olá {customer}, a encomenda {order} está pronta."]);
        $order = Order::create([
            "order_number" => "ORD-2026-0099", "customer_id" => $customer->id, "status" => "ready",
            "received_at" => now()->toDateString(), "total_price" => 500,
        ]);

        $this->actingAs($cashier)->post("/messages/clients/send-template", [
            "customer_id" => $customer->id, "template_id" => $template->id, "order_id" => $order->id,
        ])->assertRedirect("/messages/clients/258860000000");

        $message = WhatsAppMessage::firstOrFail();
        $this->assertSame("Olá Residencial, a encomenda ORD-2026-0099 está pronta.", $message->body);
        $this->assertSame($template->id, $message->template_id);
        $this->assertSame($order->id, $message->order_id);
    }

    public function test_approved_template_is_sent_as_meta_template_outside_the_window(): void
    {
        $gateway = $this->useGateway(live: true);
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Hotel Sol", "phone" => "841234567", "address" => "Matola"]);
        $template = MessageTemplate::create([
            "name" => "Pronto", "body" => "Olá {customer}, {order} pronta.",
            "wa_template_name" => "pedido_pronto", "wa_language" => "pt_PT", "wa_params" => ["customer", "order"],
        ]);
        Order::create(["order_number" => "ORD777", "customer_id" => $customer->id, "status" => "ready", "received_at" => now(), "total_price" => 100]);

        $this->actingAs($cashier)->post("/messages/clients/send-template", ["customer_id" => $customer->id, "template_id" => $template->id]);

        $this->assertSame([["template", "258841234567", "pedido_pronto", "pt_PT", ["Hotel Sol", "ORD777"]]], $gateway->sent);
        $this->assertSame("template", WhatsAppMessage::first()->type);
    }

    public function test_unapproved_template_is_refused_outside_the_window_when_live(): void
    {
        $this->useGateway(live: true);
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Hotel Sol", "phone" => "841234567", "address" => "Matola"]);
        $template = MessageTemplate::create(["name" => "Livre", "body" => "Olá {customer}"]);

        $this->actingAs($cashier)->post("/messages/clients/send-template", ["customer_id" => $customer->id, "template_id" => $template->id])
            ->assertSessionHas("error", __("app.wa_template_not_approved"));
    }

    public function test_provider_rejection_is_shown_as_failed(): void
    {
        $gateway = $this->useGateway(live: false);
        $gateway->fail = "[131026] Message undeliverable";
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "X", "phone" => "841234567", "address" => "Matola"]);
        $this->inbound("258841234567", "Olá", $customer);

        $this->actingAs($cashier)->post("/messages/clients/258841234567/reply", ["body" => "Teste"]);

        $out = WhatsAppMessage::where("direction", "out")->firstOrFail();
        $this->assertSame("failed", $out->status);
        $this->assertStringContainsString("131026", $out->error);
    }

    public function test_template_rejects_customer_without_phone(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Sem Contacto", "phone" => "", "address" => "Matola"]);
        $template = MessageTemplate::create(["name" => "T", "body" => "oi"]);

        $this->actingAs($cashier)->post("/messages/clients/send-template", ["customer_id" => $customer->id, "template_id" => $template->id])
            ->assertSessionHas("error", __("app.customer_without_phone"));

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_poll_returns_new_messages_and_statuses(): void
    {
        $cashier = User::factory()->cashier()->create();
        $first = $this->inbound("258841234567", "primeira");
        $second = $this->inbound("258841234567", "segunda");

        $this->actingAs($cashier)->getJson("/messages/clients/258841234567/poll?since={$first->id}")
            ->assertOk()
            ->assertJsonCount(1, "messages")
            ->assertJsonPath("messages.0.body", "segunda");

        $this->assertNotNull($second->fresh()->read_by_staff_at);
    }

    public function test_conversation_list_shows_unread_badge(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = Customer::create(["name" => "Cliente Novo", "phone" => "841234567", "address" => "Matola"]);
        $this->inbound("258841234567", "uma", $customer);
        $this->inbound("258841234567", "duas", $customer);

        $this->actingAs($cashier)->getJson("/messages/clients/unread-count")->assertJson(["count" => 2]);
        $this->actingAs($cashier)->get("/messages/clients")->assertOk()->assertSee("Cliente Novo")->assertSee("duas");
    }

    public function test_invalid_phone_in_url_is_not_found(): void
    {
        $this->actingAs(User::factory()->cashier()->create())->get("/messages/clients/123")->assertNotFound();
    }

    private function inbound(string $phone, string $body, ?Customer $customer = null): WhatsAppMessage
    {
        return WhatsAppMessage::create([
            "phone" => $phone, "customer_id" => $customer?->id, "direction" => "in", "type" => "text",
            "body" => $body, "status" => "received", "provider_message_id" => "wamid." . uniqid(),
        ]);
    }

    private function useGateway(bool $live): FakeGateway
    {
        $gateway = new FakeGateway($live);
        $this->app->instance(WhatsAppGateway::class, $gateway);

        return $gateway;
    }
}

/** Records what would be sent to Meta. */
class FakeGateway implements WhatsAppGateway
{
    public array $sent = [];

    public ?string $fail = null;

    public function __construct(private bool $live) {}

    public function sendText(string $to, string $body): string
    {
        if ($this->fail) {
            throw new WhatsAppException($this->fail);
        }
        $this->sent[] = ["text", $to, $body];

        return "wamid.fake" . count($this->sent);
    }

    public function sendTemplate(string $to, string $template, string $language, array $parameters): string
    {
        if ($this->fail) {
            throw new WhatsAppException($this->fail);
        }
        $this->sent[] = ["template", $to, $template, $language, $parameters];

        return "wamid.fake" . count($this->sent);
    }

    public function isLive(): bool
    {
        return $this->live;
    }

    /** A live fake behaves like Meta's official API (24h window, templates). */
    public function isOfficial(): bool
    {
        return $this->live;
    }

    public function channel(): string
    {
        return $this->live ? "cloud" : "fake";
    }
}
