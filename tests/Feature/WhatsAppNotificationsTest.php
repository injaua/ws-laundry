<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    public function test_new_order_sends_order_received_once(): void
    {
        $this->enable("order_received", "Recebida {customer} {order} {delivery_date}");
        $customer = $this->customer();
        $service = Service::create(["name" => "Lavar", "type" => "by_piece", "price" => 100, "estimated_days" => 2]);

        $this->actingAs(User::factory()->create())->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->toDateString(),
            "items" => [["service_id" => $service->id, "amount" => 1]],
        ])->assertRedirect();

        $order = Order::firstOrFail();
        $message = WhatsAppMessage::where("notification_key", "order_received:{$order->id}")->firstOrFail();
        $this->assertSame("Recebida Ana {$order->order_number} " . now()->addDays(2)->format("d/m/Y"), $message->body);
        $this->assertSame("sent", $message->status);
        $this->assertSame($order->id, $message->order_id);
    }

    public function test_disabled_event_sends_nothing(): void
    {
        $this->enable("order_ready", "Pronta {order}");
        SiteSetting::set("notify_order_ready_enabled", "0");
        $order = $this->order("ironing");

        $this->actingAs(User::factory()->create())->put("/orders/{$order->id}", $this->update($order, "ready"));

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_order_becoming_ready_notifies_once(): void
    {
        $this->enable("order_ready", "Pronta {order}");
        $order = $this->order("ironing");
        $admin = User::factory()->create();

        $this->actingAs($admin)->put("/orders/{$order->id}", $this->update($order, "ready"));
        $this->actingAs($admin)->put("/orders/{$order->id}", $this->update($order->fresh(), "ready"));

        $this->assertSame(1, WhatsAppMessage::where("notification_key", "order_ready:{$order->id}")->count());
        $this->assertNotNull($order->fresh()->ready_at);
    }

    public function test_opted_out_customer_is_skipped(): void
    {
        $this->enable("order_ready", "Pronta {order}");
        $order = $this->order("ironing");
        $order->customer->update(["whatsapp_opt_out" => true]);

        $this->actingAs(User::factory()->create())->put("/orders/{$order->id}", $this->update($order, "ready"));

        $this->assertSame(0, WhatsAppMessage::count());
    }

    public function test_payment_confirmation_carries_the_amount(): void
    {
        $this->enable("payment_received", "Pago {amount} Mts {order}");
        $order = $this->order("received", 5000);

        $this->actingAs(User::factory()->create())->post("/payments", [
            "order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 2500, "method" => "cash",
        ]);

        $this->assertSame("Pago 2,500 Mts {$order->order_number}", WhatsAppMessage::firstOrFail()->body);
    }

    public function test_scheduled_run_sends_due_soon_overdue_and_unclaimed(): void
    {
        $this->enable("credit_due_soon", "Vence {due_date}", 2);
        $this->enable("credit_overdue", "Venceu há {days} dias", 7);
        $this->enable("unclaimed", "Pronta há {days} dias", 7);

        $dueSoon = $this->order("completed", 1000, ["is_credit" => true, "due_date" => now()->addDays(2)->toDateString()]);
        $overdue = $this->order("completed", 1000, ["is_credit" => true, "due_date" => now()->subDay()->toDateString()]);
        $unclaimed = $this->order("ready", 1000);
        $unclaimed->forceFill(["ready_at" => now()->subDays(7)])->save();
        $this->order("ready", 1000); // ready today: nothing yet

        $this->artisan("whatsapp:notify-scheduled")->assertSuccessful();

        $this->assertTrue(WhatsAppMessage::where("notification_key", "like", "credit_due_soon:{$dueSoon->id}:%")->exists());
        $this->assertSame("Venceu há 1 dias", WhatsAppMessage::where("notification_key", "like", "credit_overdue:{$overdue->id}:%")->value("body"));
        $this->assertSame("Pronta há 7 dias", WhatsAppMessage::where("notification_key", "unclaimed:{$unclaimed->id}:7")->value("body"));
        $this->assertSame(3, WhatsAppMessage::count());

        // Running again the same day sends nothing new.
        $this->artisan("whatsapp:notify-scheduled")->assertSuccessful();
        $this->assertSame(3, WhatsAppMessage::count());
    }

    public function test_settings_page_saves_events_and_requires_a_template(): void
    {
        $owner = User::factory()->owner()->create();
        $template = MessageTemplate::create(["name" => "Pronta", "body" => "Pronta {order}"]);

        $this->actingAs($owner)->get("/admin/notifications")->assertOk()->assertSee(__("app.wa_event_order_ready"));

        $this->actingAs($owner)->post("/admin/notifications", ["events" => ["order_ready" => ["enabled" => 1]]])
            ->assertSessionHasErrors("events.order_ready.template_id");

        $this->actingAs($owner)->post("/admin/notifications", ["events" => [
            "order_ready" => ["enabled" => 1, "template_id" => $template->id],
            "unclaimed" => ["enabled" => 0, "days" => 10],
        ]])->assertSessionHasNoErrors();

        $this->assertSame("1", SiteSetting::get("notify_order_ready_enabled"));
        $this->assertEquals($template->id, SiteSetting::get("notify_order_ready_template_id"));
        $this->assertEquals(10, SiteSetting::get("notify_unclaimed_days"));
    }

    public function test_cashier_cannot_open_settings(): void
    {
        $this->actingAs(User::factory()->cashier()->create())->get("/admin/notifications")->assertForbidden();
    }

    public function test_template_can_be_linked_to_a_meta_template(): void
    {
        $admin = User::factory()->create();
        $template = MessageTemplate::create(["name" => "Pronta", "body" => "Pronta {order}"]);

        $this->actingAs($admin)->put("/admin/notifications/templates/{$template->id}", [
            "name" => "Pronta", "body" => "Olá {customer}, {order} pronta", "is_active" => 1,
            "wa_template_name" => "pedido_pronto", "wa_language" => "pt_PT", "wa_params" => "customer, {order}",
        ])->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame("pedido_pronto", $template->wa_template_name);
        $this->assertSame(["customer", "order"], $template->wa_params);

        $this->actingAs($admin)->put("/admin/notifications/templates/{$template->id}", [
            "name" => "Pronta", "body" => "x", "wa_params" => "customer, telefone",
        ])->assertSessionHasErrors("wa_params");
    }

    private function enable(string $event, string $body, ?int $days = null): MessageTemplate
    {
        $template = MessageTemplate::create(["name" => "T " . $event, "body" => $body]);
        SiteSetting::set("notify_{$event}_enabled", "1");
        SiteSetting::set("notify_{$event}_template_id", $template->id);
        if ($days) {
            SiteSetting::set("notify_{$event}_days", $days);
        }

        return $template;
    }

    private function customer(): Customer
    {
        return Customer::create(["name" => "Ana", "phone" => "84" . str_pad((string) ++$this->seq, 7, "0", STR_PAD_LEFT), "address" => "Matola"]);
    }

    private function order(string $status, int $total = 1000, array $extra = []): Order
    {
        return Order::create($extra + [
            "order_number" => "ORDWA" . (++$this->seq),
            "customer_id" => $this->customer()->id,
            "received_at" => now()->toDateString(),
            "total_price" => $total,
            "status" => $status,
        ]);
    }

    private function update(Order $order, string $status): array
    {
        return ["customer_id" => $order->customer_id, "status" => $status, "received_at" => $order->received_at->format("Y-m-d")];
    }
}
