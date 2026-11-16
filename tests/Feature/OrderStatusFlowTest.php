<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_backward_status_change_is_rejected(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("delivering");

        $response = $this->actingAs($user)->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id,
            "status" => "ready",
            "received_at" => $order->received_at->format("Y-m-d"),
        ]);

        $response->assertSessionHas("error", __('app.status_cannot_go_back'));
        $this->assertSame("delivering", $order->fresh()->status);
    }

    public function test_backward_status_change_is_rejected_for_every_previous_state(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("ironing");

        foreach (array_slice(Order::STATUSES, 0, $order->statusRank()) as $previous) {
            $this->actingAs($user)->put("/orders/{$order->id}", [
                "customer_id" => $order->customer_id,
                "status" => $previous,
                "received_at" => $order->received_at->format("Y-m-d"),
            ])->assertSessionHas("error");
        }

        $this->assertSame("ironing", $order->fresh()->status);
    }

    public function test_forward_and_current_status_are_accepted(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("received");

        $this->actingAs($user)->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id,
            "status" => "ready",
            "received_at" => $order->received_at->format("Y-m-d"),
        ])->assertRedirect();

        $this->assertSame("ready", $order->fresh()->status);
    }

    public function test_completed_order_stays_locked(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("completed");

        $this->actingAs($user)->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id,
            "status" => "ready",
            "received_at" => $order->received_at->format("Y-m-d"),
        ])->assertSessionHas("error", __('app.order_locked_error'));

        $this->assertSame("completed", $order->fresh()->status);
    }

    public function test_status_select_offers_only_forward_states(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("ready");

        $this->actingAs($user)->get("/orders/{$order->id}")
            ->assertDontSee('value="washing"', false)
            ->assertSee('value="delivering"', false)
            ->assertSee('value="completed"', false);

        $this->assertSame(["ready", "delivering", "completed"], $order->allowedStatuses());
    }

    public function test_fully_paid_order_advances_to_delivering(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("washing");

        $this->actingAs($user)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => $order->total_price,
            "method" => "cash",
        ])->assertRedirect();

        $this->assertSame("delivering", $order->fresh()->status);
    }

    public function test_fully_paid_order_does_not_move_a_completed_order_backwards(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder("completed");

        $this->actingAs($user)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => $order->total_price,
            "method" => "cash",
        ]);

        $this->assertSame("completed", $order->fresh()->status);
    }

    private function makeOrder(string $status): Order
    {
        $customer = Customer::create([
            "name" => "Flow Customer",
            "phone" => "+258840000001",
            "address" => "Matola",
        ]);
        $service = Service::create([
            "name" => "Wet Wash",
            "type" => "by_weight",
            "price" => 4000,
            "estimated_days" => 1,
        ]);

        $order = Order::create([
            "order_number" => "ORDFLOW" . strtoupper(substr($status, 0, 4)) . random_int(1000, 9999),
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 8000,
            "status" => $status,
        ]);

        OrderItem::create([
            "order_id" => $order->id,
            "service_id" => $service->id,
            "type" => "by_weight",
            "weight" => 2,
            "amount" => 1,
            "unit_price" => 4000,
            "subtotal" => 8000,
        ]);

        return $order;
    }
}
