<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Service;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusLookupTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $number = "ORDTEST0001"): Order
    {
        $customer = Customer::create(["name" => "Test Customer", "phone" => "+258840000001", "address" => "Test Address"]);
        $service = Service::create(["name" => "Wet Wash", "type" => "by_weight", "price" => 4000, "estimated_days" => 1]);

        $order = Order::create([
            "order_number" => $number,
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 8000,
            "status" => "received",
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

    public function test_exact_order_number_is_found(): void
    {
        $this->makeOrder();

        $this->get("/order-status?order_number=ORDTEST0001")
            ->assertOk()
            ->assertSee("Test Customer");
    }

    public function test_lowercase_order_number_is_found(): void
    {
        $this->makeOrder();

        $this->get("/order-status?order_number=ordtest0001")
            ->assertOk()
            ->assertSee("Test Customer");
    }

    public function test_order_number_with_spaces_is_found(): void
    {
        $this->makeOrder();

        $this->get("/order-status?order_number=%20ORDTEST0001%20")
            ->assertOk()
            ->assertSee("Test Customer");
    }

    public function test_unknown_order_number_shows_not_found(): void
    {
        $this->makeOrder();

        $this->get("/order-status?order_number=ORDNOPE9999")
            ->assertOk()
            ->assertSee("ORDNOPE9999")
            ->assertDontSee("Test Customer");
    }

    public function test_blank_query_shows_no_result(): void
    {
        $this->makeOrder();

        $this->get("/order-status")
            ->assertOk()
            ->assertDontSee("Test Customer");
    }
}
