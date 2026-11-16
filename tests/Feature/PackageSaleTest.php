<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageSaleTest extends TestCase
{
    use RefreshDatabase;

    private function package(): Package
    {
        return Package::create([
            "name" => "Pacote Familia",
            "weight_kg" => 5,
            "price" => 9000,
            "is_active" => true,
        ]);
    }

    public function test_order_can_sell_packages(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "C1", "phone" => "+258840000001", "address" => "A"]);
        $package = $this->package();

        $response = $this->actingAs($admin)->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 18000,
            "items" => [
                ["package_id" => $package->id, "amount" => 2, "price" => 9000, "subtotal" => 18000],
            ],
        ]);

        $response->assertRedirect();
        $item = OrderItem::latest("id")->first();
        $this->assertSame("by_package", $item->type);
        $this->assertSame($package->id, (int) $item->package_id);
        $this->assertNull($item->service_id);
        $this->assertSame(2, (int) $item->amount);
        $this->assertEquals(10, (float) $item->weight); // 2 x 5 kg of laundry load
        $this->assertEquals(18000, (float) $item->subtotal);

        $this->actingAs($admin)->get("/orders/{$item->order_id}")
            ->assertOk()
            ->assertSee("Pacote Familia");
    }

    public function test_order_mixes_services_and_packages(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "C2", "phone" => "+258840000002", "address" => "B"]);
        $service = Service::create(["name" => "Wet Wash", "type" => "by_weight", "price" => 4000, "estimated_days" => 1]);
        $package = $this->package();

        $this->actingAs($admin)->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 17000,
            "items" => [
                ["service_id" => $service->id, "weight" => 2, "price" => 4000, "subtotal" => 8000],
                ["package_id" => $package->id, "amount" => 1, "price" => 9000, "subtotal" => 9000],
            ],
        ])->assertRedirect();

        $items = OrderItem::latest("id")->limit(2)->get()->keyBy("type");
        $this->assertSame("by_weight", $items["by_weight"]->type);
        $this->assertSame("by_package", $items["by_package"]->type);
    }

    public function test_item_without_service_or_package_is_rejected(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "C3", "phone" => "+258840000003", "address" => "C"]);

        $this->actingAs($admin)->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 1000,
            "items" => [
                ["price" => 1000, "subtotal" => 1000],
            ],
        ])->assertSessionHasErrors("items.0.service_id");

        $this->assertSame(0, OrderItem::count());
    }

    public function test_landing_page_shows_only_active_packages(): void
    {
        Package::create(["name" => "Pacote Activo", "weight_kg" => 5, "price" => 9000, "is_active" => true]);
        Package::create(["name" => "Pacote Inactivo", "weight_kg" => 8, "price" => 12000, "is_active" => false]);

        $this->get("/")
            ->assertOk()
            ->assertSee("Pacote Activo")
            ->assertSee("Inclui 5 kg de roupa")
            ->assertDontSee("Pacote Inactivo");
    }

    public function test_deleted_package_keeps_order_item_history(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "C4", "phone" => "+258840000004", "address" => "D"]);
        $package = $this->package();

        $this->actingAs($admin)->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 9000,
            "items" => [
                ["package_id" => $package->id, "amount" => 1, "price" => 9000, "subtotal" => 9000],
            ],
        ])->assertRedirect();

        $item = OrderItem::latest("id")->first();
        $package->delete();

        $this->assertNull($item->fresh()->package_id);
        $this->assertSame("Pacote removido", $item->fresh()->item_name);
    }
}
