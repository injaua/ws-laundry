<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\OrderPiece;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\PieceTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PieceTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_piece_services_get_a_tagged_piece_per_unit(): void
    {
        $order = $this->createOrder(shirts: 3, kilos: 4);

        $pieces = $order->pieces;
        $this->assertCount(3, $pieces);
        $this->assertSame(
            [$order->order_number . "-01", $order->order_number . "-02", $order->order_number . "-03"],
            $pieces->pluck("code")->all(),
        );
        $this->assertSame(["Camisa"], $pieces->pluck("description")->unique()->values()->all());
        $this->assertSame("received", $pieces->first()->status);
        $this->assertCount(1, $pieces->first()->events);
    }

    public function test_scan_moves_a_piece_forward_and_records_who_and_when(): void
    {
        $cashier = User::factory()->cashier()->create();
        $piece = $this->createOrder(shirts: 2)->pieces->first();

        $this->actingAs($cashier)->postJson("/tracking/scan", ["code" => strtolower($piece->code), "stage" => "washing"])
            ->assertOk()
            ->assertJson(["ok" => true, "order" => ["progress" => ["total" => 2, "ready" => 0]]]);

        $piece->refresh();
        $this->assertSame("washing", $piece->status);
        $event = $piece->events->last();
        $this->assertSame(["received", "washing", $cashier->id], [$event->from_status, $event->to_status, $event->user_id]);
    }

    public function test_pieces_cannot_go_back_and_rescans_are_harmless(): void
    {
        $user = User::factory()->create();
        $piece = $this->createOrder(shirts: 1)->pieces->first();

        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $piece->code, "stage" => "ironing"])->assertOk();
        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $piece->code, "stage" => "ironing"])
            ->assertOk()->assertJson(["message" => __("app.scan_already_at", ["stage" => __("app.status_ironing")])]);
        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $piece->code, "stage" => "washing"])
            ->assertStatus(422);

        $this->assertSame("ironing", $piece->fresh()->status);
        $this->assertCount(2, $piece->events()->get());
    }

    public function test_order_follows_its_slowest_piece_up_to_ready(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder(shirts: 2);
        [$a, $b] = $order->pieces->all();

        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $a->code, "stage" => "ready"]);
        $this->assertSame("received", $order->fresh()->status);

        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $b->code, "stage" => "washing"]);
        $this->assertSame("washing", $order->fresh()->status);

        // Handing pieces over does not complete the order: that stays a deliberate step.
        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $b->code, "stage" => "completed"])
            ->assertJsonFragment(["warnings" => [__("app.scan_order_advanced", ["order" => $order->order_number, "stage" => __("app.status_ready")])]]);
        $this->assertSame("ready", $order->fresh()->status);
        $this->assertNotNull($order->fresh()->ready_at);
    }

    public function test_last_piece_ready_sends_the_ready_notification(): void
    {
        $template = MessageTemplate::create(["name" => "Pronta", "body" => "Pronta {order}"]);
        SiteSetting::set("notify_order_ready_enabled", "1");
        SiteSetting::set("notify_order_ready_template_id", $template->id);
        $order = $this->createOrder(shirts: 1);

        $this->actingAs(User::factory()->create())->postJson("/tracking/scan", ["code" => $order->pieces->first()->code, "stage" => "ready"]);

        $this->assertTrue(WhatsAppMessage::where("notification_key", "order_ready:{$order->id}")->exists());
    }

    public function test_scanning_the_order_number_moves_all_its_pieces(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder(shirts: 3);
        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $order->pieces->first()->code, "stage" => "ironing"]);

        $this->actingAs($user)->postJson("/tracking/scan", ["code" => $order->order_number, "stage" => "drying"])
            ->assertOk()
            ->assertJson(["message" => __("app.scan_pieces_moved", ["count" => 2, "stage" => __("app.status_drying")])])
            ->assertJsonFragment([__("app.scan_some_ahead", ["count" => 1])]);

        $this->assertSame(["ironing", "drying", "drying"], $order->pieces()->pluck("status")->all());
        $this->assertSame("drying", $order->fresh()->status);
    }

    public function test_unknown_codes_and_bad_stages_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson("/tracking/scan", ["code" => "XYZ-99", "stage" => "washing"])
            ->assertStatus(422)->assertJson(["ok" => false, "message" => __("app.scan_unknown_code", ["code" => "XYZ-99"])]);
        $this->actingAs($user)->postJson("/tracking/scan", ["code" => "XYZ-99", "stage" => "flying"])
            ->assertStatus(422)->assertJsonValidationErrors("stage");
    }

    public function test_completing_the_order_warns_about_missing_pieces_and_hands_all_over(): void
    {
        $admin = User::factory()->create();
        $order = $this->createOrder(shirts: 2);
        [$a, $b] = $order->pieces->all();
        $this->actingAs($admin)->postJson("/tracking/scan", ["code" => $a->code, "stage" => "ready"]);

        $this->actingAs($admin)->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id, "received_at" => $order->received_at->format("Y-m-d"), "status" => "completed",
        ])->assertSessionHas("warning", fn ($w) => str_contains($w, $b->code));

        $this->assertSame(["completed", "completed"], $order->pieces()->pluck("status")->all());

        // A delivered order no longer accepts production scans.
        $this->actingAs($admin)->postJson("/tracking/scan", ["code" => $a->code, "stage" => "washing"])
            ->assertStatus(422)->assertJson(["message" => __("app.scan_order_completed", ["order" => $order->order_number])]);
    }

    public function test_pieces_of_a_weight_wash_are_counted_and_added_on_the_order_page(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->createOrder(shirts: 1, kilos: 5);
        $kgItem = $order->items->firstWhere("type", "by_weight");

        $this->actingAs($cashier)->post("/orders/{$order->id}/pieces", ["order_item_id" => $kgItem->id, "count" => 4, "description" => "Lençol"])
            ->assertRedirect()->assertSessionHas("success");

        $this->assertSame(range(1, 5), $order->pieces()->pluck("seq")->all());
        $this->assertSame(4, $order->pieces()->where("description", "Lençol")->where("order_item_id", $kgItem->id)->count());

        // A line from another order cannot be referenced.
        $other = $this->createOrder(shirts: 1);
        $this->actingAs($cashier)->post("/orders/{$order->id}/pieces", ["order_item_id" => $other->items->first()->id, "count" => 1])
            ->assertSessionHasErrors("order_item_id");
        $this->actingAs($cashier)->post("/orders/{$order->id}/pieces", ["count" => PieceTracker::MAX_BATCH + 1])
            ->assertSessionHasErrors("count");
    }

    public function test_piece_details_and_manual_stage_change(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->createOrder(shirts: 1);
        $piece = $order->pieces->first();

        $this->actingAs($cashier)->put("/orders/{$order->id}/pieces/{$piece->id}", [
            "description" => "Camisa social", "color" => "Branca", "brand" => "Zara",
            "intake_notes" => "Mancha de vinho no punho", "issue" => "", "status" => "ready",
        ])->assertRedirect();

        $piece->refresh();
        $this->assertSame(["Camisa social", "Branca", "Zara", "Mancha de vinho no punho", "ready"],
            [$piece->description, $piece->color, $piece->brand, $piece->intake_notes, $piece->status]);
        $this->assertSame(__("app.piece_event_manual"), $piece->events->last()->note);
        $this->assertSame("ready", $order->fresh()->status);

        $this->actingAs($cashier)->get("/orders/{$order->id}")->assertOk()
            ->assertSee($piece->code)->assertSee("Mancha de vinho no punho");
    }

    public function test_only_managers_delete_pieces_and_pieces_must_belong_to_the_order(): void
    {
        $order = $this->createOrder(shirts: 2);
        $other = $this->createOrder(shirts: 1);
        $piece = $order->pieces->first();

        $this->actingAs(User::factory()->cashier()->create())->delete("/orders/{$order->id}/pieces/{$piece->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())->delete("/orders/{$other->id}/pieces/{$piece->id}")->assertNotFound();
        $this->actingAs(User::factory()->create())->delete("/orders/{$order->id}/pieces/{$piece->id}")->assertRedirect();

        $this->assertNull(OrderPiece::find($piece->id));
    }

    public function test_tags_lookup_station_and_public_progress(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->createOrder(shirts: 2);
        $piece = $order->pieces->first();
        $this->actingAs($cashier)->postJson("/tracking/scan", ["code" => $piece->code, "stage" => "ready"]);

        $this->actingAs($cashier)->get("/orders/{$order->id}/pieces/labels?type=thermal")->assertOk()
            ->assertSee($piece->code)->assertSee("1/2")->assertSee("<svg", false);

        $this->actingAs($cashier)->getJson("/tracking/lookup?code={$piece->code}")->assertOk()
            ->assertJsonPath("code", $piece->code)
            ->assertJsonCount(2, "events")
            ->assertJsonPath("events.1.user", $cashier->name);

        $this->actingAs($cashier)->get("/tracking")->assertOk()->assertSee(__("app.pieces_in_progress"));

        $this->get("/order-status?order_number={$order->order_number}&phone_last4=4567")
            ->assertOk()->assertSee(__("app.pieces_ready_of", ["ready" => 1, "total" => 2]));
    }

    private function createOrder(int $shirts = 0, float $kilos = 0): Order
    {
        static $seq = 0;
        $seq++;
        $customer = Customer::create(["name" => "Cliente {$seq}", "phone" => "84" . str_pad((string) $seq, 3, "0", STR_PAD_LEFT) . "4567", "address" => "Matola"]);
        $items = [];
        if ($shirts) {
            $shirt = Service::firstOrCreate(["name" => "Camisa"], ["type" => "by_piece", "price" => 50, "estimated_days" => 2]);
            $items[] = ["service_id" => $shirt->id, "amount" => $shirts];
        }
        if ($kilos) {
            $wash = Service::firstOrCreate(["name" => "Lavagem ao kg"], ["type" => "by_weight", "price" => 120, "estimated_days" => 2]);
            $items[] = ["service_id" => $wash->id, "weight" => $kilos];
        }

        $this->actingAs(User::factory()->create())->post("/orders", [
            "customer_id" => $customer->id, "received_at" => now()->toDateString(), "items" => $items,
        ])->assertRedirect();

        return Order::where("customer_id", $customer->id)->with("pieces.events", "items")->firstOrFail();
    }
}
