<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CourierJob;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickupDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $courier;

    private Customer $customer;

    private DeliveryZone $zone;

    private Service $service;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "0");

        $this->admin = User::factory()->create();
        $this->courier = User::factory()->courier()->create(["name" => "Zito"]);
        $this->zone = DeliveryZone::create(["name" => "Polana", "fee" => 150, "free_from" => 3000, "is_active" => true]);
        $this->customer = Customer::create(["name" => "Ana", "phone" => "+258841234567", "address" => "Av. Julius Nyerere 100", "delivery_zone_id" => $this->zone->id]);
        $this->service = Service::create(["name" => "Lavar", "type" => "by_weight", "price" => 100, "estimated_days" => 1]);
    }

    private function order(float $total, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            "order_number" => "LD-" . str_pad((string) ++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $this->customer->id,
            "status" => "ready",
            "received_at" => today()->toDateString(),
            "ready_at" => now(),
            "subtotal" => $total,
            "vat_rate" => 0,
            "vat_amount" => 0,
            "total_price" => $total,
        ], $overrides));
        $order->items()->create(["service_id" => $this->service->id, "type" => "by_weight", "weight" => $total / 100, "amount" => 1, "unit_price" => 100, "subtotal" => $total]);

        return $order;
    }

    private function scheduleDelivery(Order $order, array $extra = []): CourierJob
    {
        $this->actingAs($this->admin)->post(route("logistics.store"), array_merge([
            "type" => "delivery", "order_id" => $order->id, "address" => "Av. Julius Nyerere 100",
            "delivery_zone_id" => $this->zone->id, "scheduled_date" => today()->toDateString(), "time_window" => "morning",
            "courier_id" => $this->courier->id,
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return CourierJob::latest("id")->first();
    }

    // ---- Zones ----

    public function test_zones_are_managed_by_management_only(): void
    {
        $this->actingAs($this->admin)->post(route("delivery-zones.store"), ["name" => "Matola", "fee" => 250, "free_from" => "", "is_active" => 1])
            ->assertRedirect(route("delivery-zones.index"));
        $matola = DeliveryZone::firstWhere("name", "Matola");
        $this->assertNull($matola->free_from);
        $this->assertSame(250.0, $matola->feeFor(99999));

        $this->actingAs(User::factory()->cashier()->create())->get(route("delivery-zones.index"))->assertForbidden();

        // A zone with trips is deactivated, not deleted.
        $this->order(1000)->courierJobs()->create(["type" => "delivery", "status" => "cancelled", "contact_name" => "x", "contact_phone" => "1", "address" => "x", "scheduled_date" => today(), "delivery_zone_id" => $this->zone->id]);
        $this->actingAs($this->admin)->delete(route("delivery-zones.destroy", $this->zone));
        $this->assertFalse($this->zone->fresh()->is_active);
        $this->actingAs($this->admin)->delete(route("delivery-zones.destroy", $matola));
        $this->assertModelMissing($matola);

        $this->actingAs($this->admin)->get(route("delivery-zones.index"))->assertOk()->assertSee("Polana");
        $this->actingAs($this->admin)->get(route("delivery-zones.create"))->assertOk();
        $this->actingAs($this->admin)->get(route("delivery-zones.edit", $this->zone))->assertOk();
        $this->actingAs($this->admin)->get(route("logistics.create", ["type" => "delivery", "order_id" => $this->order(500)->id]))->assertOk()->assertSee("LD-0002");
        $this->actingAs($this->admin)->get(route("customers.edit", $this->customer))->assertOk()->assertSee(__("app.address_reference"));
    }

    // ---- Delivery: fee, courier route, collection, handover ----

    public function test_delivery_adds_zone_fee_to_order_unless_free(): void
    {
        $small = $this->order(1000);
        $job = $this->scheduleDelivery($small);
        $this->assertSame(150.0, (float) $job->fee);
        $small->refresh();
        $this->assertSame(150.0, (float) $small->delivery_fee);
        $this->assertSame(1150.0, (float) $small->total_price);

        $big = $this->order(3000);
        $this->assertSame(0.0, (float) $this->scheduleDelivery($big)->fee);
        $this->assertSame(3000.0, (float) $big->fresh()->total_price);

        // Only one open delivery per order.
        $this->actingAs($this->admin)->post(route("logistics.store"), [
            "type" => "delivery", "order_id" => $small->id, "address" => "x", "scheduled_date" => today()->toDateString(), "time_window" => "any",
        ])->assertSessionHasErrors("order_id");

        // Cancelling removes the fee again.
        $this->actingAs($this->admin)->post(route("logistics.cancel", $job))->assertRedirect();
        $this->assertSame(1000.0, (float) $small->fresh()->total_price);
        $this->actingAs($this->admin)->get(route("orders.show", $small))->assertOk()->assertSee(__("app.schedule_delivery"));
    }

    public function test_courier_delivers_collects_cash_and_counter_receives_it(): void
    {
        $order = $this->order(1000);
        $job = $this->scheduleDelivery($order);
        $other = $this->scheduleDelivery($this->order(500), ["courier_id" => null]);

        $this->actingAs($this->courier)->get("/dashboard")->assertRedirect(route("courier.route"));
        $this->actingAs($this->courier)->get(route("courier.route"))->assertOk()
            ->assertSee($order->order_number)->assertSee("1,150")->assertDontSee("LD-0002");
        $this->actingAs($this->courier)->get(route("orders.index"))->assertForbidden();
        $this->actingAs($this->courier)->post(route("logistics.start", $other))->assertForbidden();

        $this->actingAs($this->courier)->post(route("logistics.start", $job))->assertRedirect(route("courier.route"));
        $this->assertSame("on_the_way", $job->fresh()->status);
        $this->assertSame("delivering", $order->fresh()->status);

        // A cash sale must be paid in full on delivery.
        $this->actingAs($this->courier)->post(route("logistics.complete", $job), ["amount" => 500, "method" => "cash"])
            ->assertSessionHasErrors("amount");

        $this->actingAs($this->courier)->post(route("logistics.complete", $job), ["amount" => 1150, "method" => "cash", "outcome_notes" => "Porteiro"])
            ->assertRedirect(route("courier.route"));

        $job->refresh();
        $order->refresh();
        $this->assertSame("done", $job->status);
        $this->assertSame("completed", $order->status);
        $this->assertSame(0.0, $order->remainingBalance());

        $payment = Payment::firstOrFail();
        $this->assertSame($this->courier->id, $payment->user_id);
        $this->assertNull($payment->cash_session_id);
        $this->assertSame(1, Payment::pendingHandover()->count());
        $this->assertNotNull(JournalEntry::where("source", "payment")->where("source_id", $payment->id)->first());

        $this->actingAs($this->courier)->get(route("courier.route"))->assertSee("1,150 Mts");

        // The counter receives it into the till; the till now expects it.
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get(route("logistics.cash"))->assertOk()->assertSee("Zito");
        $this->actingAs($cashier)->post(route("logistics.cash.receive", $this->courier))->assertSessionHasErrors("till");

        $till = $this->openTill($cashier);
        $this->actingAs($cashier)->post(route("logistics.cash.receive", $this->courier))->assertRedirect(route("logistics.cash"));
        $this->assertSame(0, Payment::pendingHandover()->count());
        $this->assertSame(1150.0, $till->fresh()->expectedCash());
    }

    public function test_credit_order_can_be_delivered_without_payment_and_electronic_needs_no_handover(): void
    {
        $credit = $this->order(2000, ["is_credit" => true]);
        $job = $this->scheduleDelivery($credit);
        $this->actingAs($this->courier)->post(route("logistics.complete", $job), ["amount" => 0])->assertRedirect();
        $this->assertSame("completed", $credit->fresh()->status);
        $this->assertSame(0, Payment::count());

        // Revenue of the credit sale is booked on delivery: D 4.1.1 Clientes / C 7.2.
        $entry = JournalEntry::where("source", "credit_sale")->where("source_id", $credit->id)->firstOrFail();
        $this->assertSame(2150.0, (float) $entry->lines()->where("account_id", Account::findByCode("4.1.1")->id)->sum("debit"));

        $order = $this->order(800);
        $job = $this->scheduleDelivery($order);
        $this->actingAs($this->courier)->post(route("logistics.complete", $job), ["amount" => 950, "method" => "mpesa", "transaction_ref" => "MPDEL950"])->assertRedirect();
        $this->assertSame(0, Payment::pendingHandover()->count());
        $this->assertNotNull(Payment::firstWhere("method", "mpesa")->handed_over_at);
    }

    public function test_unready_order_cannot_go_out_and_failed_trip_can_be_rescheduled(): void
    {
        $order = $this->order(1000, ["status" => "washing", "ready_at" => null]);
        $job = $this->scheduleDelivery($order);

        $this->actingAs($this->courier)->post(route("logistics.start", $job))->assertSessionHasErrors("status");

        $order->update(["status" => "ready"]);
        $this->actingAs($this->courier)->post(route("logistics.fail", $job), ["failure_reason" => "Cliente ausente"])->assertRedirect();
        $this->assertSame("failed", $job->fresh()->status);
        $this->assertSame(1150.0, (float) $order->fresh()->total_price); // the trip still happened

        $this->actingAs($this->admin)->put(route("logistics.update", $job), [
            "scheduled_date" => today()->addDay()->toDateString(), "time_window" => "afternoon", "courier_id" => $this->courier->id,
            "contact_name" => "Ana", "contact_phone" => "841234567", "address" => "Av. Julius Nyerere 100",
        ])->assertRedirect(route("logistics.show", $job));

        $job->refresh();
        $this->assertSame("scheduled", $job->status);
        $this->assertNull($job->failure_reason);
        $this->actingAs($this->admin)->get(route("logistics.show", $job))->assertOk();
        $this->actingAs($this->admin)->get(route("logistics.route-sheet", ["date" => today()->addDay()->toDateString()]))->assertOk()->assertSee($order->order_number);
    }

    // ---- Pickup: website request → confirmation → order ----

    public function test_website_request_is_confirmed_and_becomes_an_order_with_the_pickup_fee(): void
    {
        $this->get("/")->assertOk()->assertSee(__("app.request_pickup"));

        $this->post(route("pickup-requests.store"), [
            "contact_name" => "Ana", "contact_phone" => "84 123 4567", "address" => "Rua 1", "delivery_zone_id" => $this->zone->id,
            "scheduled_date" => today()->subDay()->toDateString(), "time_window" => "morning",
        ])->assertSessionHasErrors("scheduled_date", null, "pickup");

        $this->post(route("pickup-requests.store"), [
            "contact_name" => "Ana", "contact_phone" => "84 123 4567", "address" => "Rua 1", "delivery_zone_id" => $this->zone->id,
            "scheduled_date" => today()->addDay()->toDateString(), "time_window" => "morning",
        ])->assertRedirect(route("home") . "#pickup");

        $job = CourierJob::firstOrFail();
        $this->assertSame("requested", $job->status);
        $this->assertSame("website", $job->source);
        $this->assertNull($job->customer_id);
        $this->assertSame(150.0, (float) $job->fee);

        // Honeypot submissions are silently dropped.
        $this->post(route("pickup-requests.store"), ["website" => "spam"])->assertRedirect();
        $this->assertSame(1, CourierJob::count());

        $this->actingAs($this->admin)->get(route("logistics.index"))->assertOk()->assertSee(__("app.pickup_requests_pending"));

        // Same phone in another format → linked to the existing customer.
        $this->actingAs($this->admin)->post(route("logistics.confirm", $job), [
            "scheduled_date" => today()->addDay()->toDateString(), "time_window" => "morning", "courier_id" => $this->courier->id,
        ])->assertRedirect();
        $job->refresh();
        $this->assertSame("scheduled", $job->status);
        $this->assertSame($this->customer->id, $job->customer_id);
    }

    public function test_pickup_done_then_order_bills_the_fee_and_new_customer_is_registered(): void
    {
        $this->post(route("pickup-requests.store"), [
            "contact_name" => "Novo Cliente", "contact_phone" => "+258 87 000 1111", "address" => "Rua 2", "address_reference" => "Portão azul",
            "delivery_zone_id" => $this->zone->id, "scheduled_date" => today()->toDateString(), "time_window" => "any",
        ]);
        $job = CourierJob::firstOrFail();
        $this->actingAs($this->admin)->post(route("logistics.confirm", $job), ["scheduled_date" => today()->toDateString(), "time_window" => "any", "courier_id" => $this->courier->id]);

        $newCustomer = Customer::firstWhere("name", "Novo Cliente");
        $this->assertSame("Portão azul", $newCustomer->address_reference);
        $this->assertSame($this->zone->id, $newCustomer->delivery_zone_id);

        $this->actingAs($this->courier)->post(route("logistics.start", $job->fresh()))->assertRedirect();
        $this->actingAs($this->courier)->post(route("logistics.complete", $job), ["outcome_notes" => "2 sacos"])->assertRedirect(route("courier.route"));
        $this->assertTrue($job->fresh()->awaitsOrder());

        $this->actingAs($this->admin)->get(route("orders.create", ["pickup" => $job->id]))->assertOk()->assertSee('name="pickup_job_id"', false);

        $this->actingAs($this->admin)->post("/orders", [
            "customer_id" => $newCustomer->id, "received_at" => today()->toDateString(), "pickup_job_id" => $job->id,
            "items" => [["service_id" => $this->service->id, "weight" => 5]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::latest("id")->first();
        $this->assertSame(150.0, (float) $order->delivery_fee);
        $this->assertSame(650.0, (float) $order->total_price);
        $this->assertSame($order->id, $job->fresh()->order_id);

        // The same pickup cannot be billed twice.
        $this->actingAs($this->admin)->post("/orders", [
            "customer_id" => $newCustomer->id, "received_at" => today()->toDateString(), "pickup_job_id" => $job->id,
            "items" => [["service_id" => $this->service->id, "weight" => 1]],
        ])->assertSessionHasErrors("pickup_job_id");
    }

    public function test_pickup_over_free_threshold_is_free_and_counter_pickup_from_customer_page(): void
    {
        $this->actingAs($this->admin)->get(route("customers.show", $this->customer))->assertOk()->assertSee(__("app.schedule_pickup"));
        $this->actingAs($this->admin)->get(route("logistics.create", ["type" => "pickup", "customer_id" => $this->customer->id]))->assertOk()->assertSee("Av. Julius Nyerere 100");

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->post(route("logistics.store"), [
            "type" => "pickup", "customer_id" => $this->customer->id, "address" => "Av. Julius Nyerere 100", "delivery_zone_id" => $this->zone->id,
            "scheduled_date" => today()->toDateString(), "time_window" => "any", "fee" => 0, // cashiers cannot change the fee
        ])->assertRedirect();
        $job = CourierJob::firstOrFail();
        $this->assertSame(150.0, (float) $job->fee);
        $this->assertSame("scheduled", $job->status);

        $this->actingAs($cashier)->post(route("logistics.complete", $job))->assertRedirect(route("logistics.show", $job));

        $this->actingAs($cashier)->post("/orders", [
            "customer_id" => $this->customer->id, "received_at" => today()->toDateString(), "pickup_job_id" => $job->id,
            "items" => [["service_id" => $this->service->id, "weight" => 30]],
        ])->assertSessionHasNoErrors();

        $order = Order::latest("id")->first();
        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertSame(3000.0, (float) $order->total_price);
        $this->assertSame(0.0, (float) $job->fresh()->fee);
    }

    public function test_courier_role_can_be_assigned_and_courier_sees_only_route(): void
    {
        $this->actingAs($this->admin)->post(route("users.store"), [
            "name" => "Estafeta Dois", "email" => "e2@example.com", "password" => "Password2026", "password_confirmation" => "Password2026",
            "role" => "courier", "is_active" => 1,
        ])->assertRedirect();
        $this->assertSame("courier", User::firstWhere("email", "e2@example.com")->role);

        $this->actingAs($this->courier)->get(route("courier.route"))->assertOk()
            ->assertSee(__("app.my_route"))->assertDontSee(route("orders.index"));
        $this->actingAs($this->courier)->get(route("logistics.index"))->assertForbidden();
        $this->actingAs($this->courier)->get(route("customers.index"))->assertForbidden();
    }
}
