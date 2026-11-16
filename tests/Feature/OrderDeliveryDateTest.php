<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderDeliveryDateTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        $customer = Customer::create(["name" => "Entrega Teste", "phone" => "+258840000099", "address" => "Matola"]);

        return array_merge([
            "customer_id" => $customer->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 5000,
            "items" => [
                ["service_id" => \App\Models\Service::create(["name" => "Lavagem", "type" => "by_weight", "price" => 2500, "estimated_days" => 1])->id,
                    "weight" => 2, "price" => 2500, "subtotal" => 5000],
            ],
        ], $overrides);
    }

    public function test_order_can_be_created_with_delivery_date(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/orders", $this->payload([
            "delivery_date" => now()->addDays(3)->format("Y-m-d"),
        ]))->assertRedirect();

        $order = Order::latest("id")->first();
        $this->assertEquals(now()->addDays(3)->toDateString(), $order->delivery_date->toDateString());

        $this->actingAs($admin)->get("/orders/" . $order->id)
            ->assertOk()
            ->assertSee("Data de Entrega");
    }

    public function test_delivery_date_is_auto_filled_from_service_lead_time(): void
    {
        $admin = User::factory()->create();

        // Payload service has estimated_days = 1, so delivery = entry date + 1 day.
        $this->actingAs($admin)->post("/orders", $this->payload())->assertRedirect();

        $order = Order::latest("id")->first();
        $this->assertEquals(now()->addDay()->toDateString(), $order->delivery_date->toDateString());
    }

    public function test_delivery_date_stays_empty_when_no_service_lead_time(): void
    {
        $admin = User::factory()->create();

        $package = \App\Models\Package::create(["name" => "Pacote X", "weight_kg" => 5, "price" => 5000, "is_active" => true]);

        $this->actingAs($admin)->post("/orders", [
            "customer_id" => \App\Models\Customer::create(["name" => "P", "phone" => "+258840000011", "address" => "A"])->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 5000,
            "items" => [
                ["package_id" => $package->id, "amount" => 1, "price" => 5000, "subtotal" => 5000],
            ],
        ])->assertRedirect();

        $this->assertNull(Order::latest("id")->first()->delivery_date);
    }

    public function test_manual_delivery_date_overrides_lead_time_estimate(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/orders", $this->payload([
            "delivery_date" => now()->addDays(10)->format("Y-m-d"),
        ]))->assertRedirect();

        $this->assertEquals(now()->addDays(10)->toDateString(), Order::latest("id")->first()->delivery_date->toDateString());
    }

    public function test_delivery_date_before_entry_date_is_rejected(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/orders", $this->payload([
            "delivery_date" => now()->subDay()->format("Y-m-d"),
        ]))->assertSessionHasErrors("delivery_date");

        $this->assertSame(0, Order::count());
    }

    public function test_expected_completion_date_falls_back_to_delivery_date_on_detail_page(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "F", "phone" => "+258840000012", "address" => "A"]);
        $order = Order::create([
            "order_number" => "ORD-TEST-2",
            "customer_id" => $customer->id,
            "received_at" => now()->toDateString(),
            "delivery_date" => now()->addDays(3)->toDateString(),
            "status" => "received",
            "total_price" => 1200,
        ]);

        $expected = now()->addDays(3)->format("d/m/Y");
        $this->actingAs($admin)->get("/orders/" . $order->id)
            ->assertOk()
            ->assertSee($expected)
            ->assertSee('value="' . now()->addDays(3)->toDateString() . '"', false);
    }

    public function test_admin_can_update_delivery_date(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "C", "phone" => "+258840000008", "address" => "A"]);
        $order = Order::create([
            "order_number" => "ORD-TEST-1",
            "customer_id" => $customer->id,
            "received_at" => now()->toDateString(),
            "status" => "received",
            "total_price" => 5000,
        ]);

        $this->actingAs($admin)->patch("/orders/" . $order->id, [
            "customer_id" => $customer->id,
            "status" => "received",
            "received_at" => now()->toDateString(),
            "delivery_date" => now()->addDays(2)->toDateString(),
        ])->assertRedirect();

        $this->assertEquals(now()->addDays(2)->toDateString(), $order->fresh()->delivery_date->toDateString());
    }
}
