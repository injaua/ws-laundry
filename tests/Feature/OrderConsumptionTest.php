<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\PackageRecipe;
use App\Models\Service;
use App\Models\ServiceRecipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderConsumptionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function service(string $type = "by_weight"): Service
    {
        return Service::create([
            "name" => "Wet Wash",
            "type" => $type,
            "price" => 4000,
            "estimated_days" => 1,
        ]);
    }

    private function detergent(float $stockQty = 100, string $name = "Detergente"): InventoryItem
    {
        $item = InventoryItem::create([
            "kind" => "product",
            "name" => $name,
            "type" => "detergent",
            "unit" => "l",
        ]);

        if ($stockQty > 0) {
            $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
                "direction" => "in",
                "quantity" => $stockQty,
                "unit_cost" => 2500,
                "reason" => "purchase",
                "moved_at" => now()->toDateString(),
            ])->assertSessionHasNoErrors();
        }

        return $item;
    }

    private function order(Service $service, array $line): Order
    {
        $customer = Customer::create([
            "name" => "BOM Customer",
            "phone" => "+258840000099",
            "address" => "Matola",
        ]);

        $order = Order::create([
            "order_number" => "ORDBOM".random_int(1000, 9999),
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 8000,
            "subtotal" => 8000,
            "status" => "received",
        ]);

        OrderItem::create(array_merge([
            "order_id" => $order->id,
            "service_id" => $service->id,
            "type" => "by_weight",
            "weight" => 2,
            "amount" => 1,
            "unit_price" => 4000,
            "subtotal" => 8000,
        ], $line));

        return $order;
    }

    private function advance(Order $order, string $status, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin())->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id,
            "status" => $status,
            "received_at" => $order->received_at->format("Y-m-d"),
        ]);
    }

    public function test_washing_deducts_recipe_per_kg(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing")->assertRedirect();

        $movement = InventoryMovement::firstWhere("source_type", "order");
        $this->assertNotNull($movement);
        $this->assertSame("out", $movement->direction);
        $this->assertSame("consumption", $movement->reason);
        $this->assertSame($order->id, $movement->source_id);
        $this->assertSame("0.10", (string) $movement->quantity);
        $this->assertSame("99.90", (string) $item->fresh()->stock_qty);
        $this->assertNotNull($order->fresh()->materials_consumed_at);
    }

    public function test_recipe_per_piece_deducts_by_amount(): void
    {
        $item = $this->detergent();
        $service = $this->service("by_piece");
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.01, "basis" => "per_piece"]);
        $order = $this->order($service, ["type" => "by_piece", "weight" => null, "amount" => 10]);

        $this->advance($order, "washing")->assertRedirect();

        $this->assertSame("0.10", (string) InventoryMovement::firstWhere("source_type", "order")->quantity);
    }

    public function test_package_line_maps_to_kg(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.1, "basis" => "per_kg"]);

        $package = Package::create(["name" => "Family pack", "weight_kg" => 5, "price" => 1500, "is_active" => 1]);
        $order = $this->order($service, ["type" => "by_package", "package_id" => $package->id, "weight" => null, "amount" => 2]);

        $this->advance($order, "washing")->assertRedirect();

        // 2 packages x 5 kg x 0.1 l/kg = 1
        $this->assertSame("1.00", (string) InventoryMovement::firstWhere("source_type", "order")->quantity);
    }

    public function test_consumption_is_idempotent_on_later_transitions(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing");
        $this->advance($order->fresh(), "drying");
        $this->advance($order->fresh(), "completed");

        $this->assertSame(1, InventoryMovement::where("source_type", "order")->count());
        $this->assertSame("99.90", (string) $item->fresh()->stock_qty);
    }

    public function test_forward_jump_past_washing_consumes_once(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "completed");

        $this->assertSame(1, InventoryMovement::where("source_type", "order")->count());
        $this->assertSame("99.90", (string) $item->fresh()->stock_qty);
    }

    public function test_order_without_recipe_creates_no_movement(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing");

        $this->assertSame(0, InventoryMovement::where("source_type", "order")->count());
        $this->assertNotNull($order->fresh()->materials_consumed_at);
        $this->assertSame(["washing"], $order->fresh()->consumed_stages);
        $this->assertSame("100.00", (string) $item->fresh()->stock_qty);
    }

    public function test_insufficient_stock_deducts_available_and_warns(): void
    {
        $item = $this->detergent(0.05);
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]); // needs 0.1, has 0.05

        $this->advance($order, "washing")
            ->assertRedirect()
            ->assertSessionHas("warning");

        $this->assertSame("0.00", (string) $item->fresh()->stock_qty);
        $this->assertSame("0.05", (string) InventoryMovement::firstWhere("source_type", "order")->quantity);
        $this->assertSame("washing", $order->fresh()->status);
    }

    public function test_order_paid_in_advance_stays_in_production_without_consuming(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->actingAs($this->admin())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => $order->total_price,
            "method" => "cash",
        ])->assertRedirect();

        // Paying up front does not wash the clothes: the order stays on the production board.
        $this->assertSame("received", $order->fresh()->status);
        $this->assertSame("100.00", (string) $item->fresh()->stock_qty);
    }

    public function test_cashier_status_change_triggers_consumption(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing", User::factory()->cashier()->create());

        $this->assertSame("99.90", (string) $item->fresh()->stock_qty);
    }

    public function test_order_sourced_movements_are_locked_in_ledger(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg"]);
        $order = $this->order($service, ["weight" => 2]);
        $this->advance($order, "washing");

        $movement = InventoryMovement::firstWhere("source_type", "order");

        $this->actingAs($this->admin())->get("/resources/movements/{$movement->id}/edit")
            ->assertSessionHas("error", __("app.movement_locked_via_job"));
        $this->actingAs($this->admin())->delete("/resources/movements/{$movement->id}")
            ->assertSessionHas("error", __("app.movement_locked_via_job"));

        $this->assertSame("99.90", (string) $item->fresh()->stock_qty);
    }

    public function test_each_stage_consumes_only_its_own_recipe(): void
    {
        $detergent = $this->detergent();
        $softener = $this->detergent(100, "Amaciante");
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $detergent->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "washing"]);
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $softener->id, "quantity" => 0.02, "basis" => "per_kg", "stage" => "drying"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing")->assertRedirect();
        $this->assertSame("99.90", (string) $detergent->fresh()->stock_qty);
        $this->assertSame("100.00", (string) $softener->fresh()->stock_qty);
        $this->assertSame(["washing"], $order->fresh()->consumed_stages);

        $this->advance($order->fresh(), "drying")->assertRedirect();
        $this->assertSame("99.90", (string) $detergent->fresh()->stock_qty);
        $this->assertSame("99.96", (string) $softener->fresh()->stock_qty);
        $this->assertSame(["washing", "drying"], $order->fresh()->consumed_stages);

        // No recipe past drying: later transitions deduct nothing.
        $this->advance($order->fresh(), "completed");
        $this->assertSame(2, InventoryMovement::where("source_type", "order")->count());
    }

    public function test_forward_jump_consumes_all_skipped_stages(): void
    {
        $detergent = $this->detergent();
        $starch = $this->detergent(100, "Goma");
        $bag = $this->detergent(100, "Saco");
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $detergent->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "washing"]);
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $starch->id, "quantity" => 0.01, "basis" => "per_kg", "stage" => "ironing"]);
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $bag->id, "quantity" => 0.005, "basis" => "per_kg", "stage" => "completed"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "completed")->assertRedirect();

        $this->assertSame("99.90", (string) $detergent->fresh()->stock_qty);
        $this->assertSame("99.98", (string) $starch->fresh()->stock_qty);
        $this->assertSame("99.98", (string) $bag->fresh()->stock_qty);
        $this->assertSame(3, InventoryMovement::where("source_type", "order")->count());
        $this->assertSame(
            ["washing", "drying", "ironing", "folding", "ready", "delivering", "completed"],
            $order->fresh()->consumed_stages
        );
    }

    public function test_stage_recipe_added_after_the_stage_passed_is_not_consumed(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing");
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "washing"]);
        $this->advance($order->fresh(), "drying");

        $this->assertSame(0, InventoryMovement::where("source_type", "order")->count());
        $this->assertSame("100.00", (string) $item->fresh()->stock_qty);
    }

    public function test_same_item_can_have_recipes_on_different_stages(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "washing"]);
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.02, "basis" => "per_kg", "stage" => "drying"]);
        $order = $this->order($service, ["weight" => 2]);

        $this->advance($order, "washing");
        $this->advance($order->fresh(), "drying");

        $this->assertSame("99.86", (string) $item->fresh()->stock_qty);
        $this->assertSame(2, InventoryMovement::where("source_type", "order")->count());
    }

    public function test_recipe_form_accepts_stage_and_allows_same_item_on_new_stage(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/services/{$service->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "washing",
        ])->assertSessionHasNoErrors();

        // Same item, same stage: rejected by the (service, item, stage) uniqueness rule.
        $this->actingAs($admin)->post("/services/{$service->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "washing",
        ])->assertSessionHasErrors("inventory_item_id");

        // Same item, different stage: accepted.
        $this->actingAs($admin)->post("/services/{$service->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.02, "basis" => "per_kg", "stage" => "drying",
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $service->recipes()->count());

        // Invalid stage values are rejected.
        $this->actingAs($admin)->post("/services/{$service->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.01, "basis" => "per_kg", "stage" => "received",
        ])->assertSessionHasErrors("stage");

        $this->actingAs($admin)->get("/services/{$service->id}/edit")
            ->assertSee(__('app.recipe_stage'))
            ->assertSee(__('app.status_drying'));
    }

    public function test_package_line_consumes_package_recipes_per_stage(): void
    {
        $detergent = $this->detergent();
        $bag = $this->detergent(100, "Saco");
        $service = $this->service();
        $package = Package::create(["name" => "Family pack", "weight_kg" => 5, "price" => 1500, "is_active" => 1]);
        PackageRecipe::create(["package_id" => $package->id, "inventory_item_id" => $detergent->id, "quantity" => 0.1, "basis" => "per_kg", "stage" => "washing"]);
        PackageRecipe::create(["package_id" => $package->id, "inventory_item_id" => $bag->id, "quantity" => 1, "basis" => "per_piece", "stage" => "completed"]);
        $order = $this->order($service, ["type" => "by_package", "package_id" => $package->id, "weight" => null, "amount" => 2]);

        $this->advance($order, "washing");
        // 2 packages x 5 kg x 0.1 l/kg = 1
        $this->assertSame("99.00", (string) $detergent->fresh()->stock_qty);
        $this->assertSame("100.00", (string) $bag->fresh()->stock_qty);

        $this->advance($order->fresh(), "completed");
        // per_piece on a package line = per package: 2 x 1 = 2
        $this->assertSame("99.00", (string) $detergent->fresh()->stock_qty);
        $this->assertSame("98.00", (string) $bag->fresh()->stock_qty);
        $this->assertSame(2, InventoryMovement::where("source_type", "order")->count());
    }

    public function test_package_and_service_recipes_of_same_item_are_added_together(): void
    {
        $item = $this->detergent();
        $service = $this->service();
        $package = Package::create(["name" => "Duo pack", "weight_kg" => 3, "price" => 900, "is_active" => 1]);
        ServiceRecipe::create(["service_id" => $service->id, "inventory_item_id" => $item->id, "quantity" => 0.1, "basis" => "per_kg", "stage" => "washing"]);
        PackageRecipe::create(["package_id" => $package->id, "inventory_item_id" => $item->id, "quantity" => 0.2, "basis" => "per_kg", "stage" => "washing"]);
        $order = $this->order($service, ["type" => "by_package", "package_id" => $package->id, "weight" => null, "amount" => 2]);

        $this->advance($order, "washing");

        // service: 2 x 3 kg x 0.1 = 0.6 ; package: 2 x 3 kg x 0.2 = 1.2 ; total 1.8
        $this->assertSame("98.20", (string) $item->fresh()->stock_qty);
    }

    public function test_package_recipe_form_accepts_stage_and_validates(): void
    {
        $item = $this->detergent();
        $package = Package::create(["name" => "Form pack", "weight_kg" => 4, "price" => 1000, "is_active" => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/packages/{$package->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "drying",
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post("/packages/{$package->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "drying",
        ])->assertSessionHasErrors("inventory_item_id");

        $this->actingAs($admin)->post("/packages/{$package->id}/recipes", [
            "inventory_item_id" => $item->id, "quantity" => 0.05, "basis" => "per_kg", "stage" => "received",
        ])->assertSessionHasErrors("stage");

        $this->actingAs($admin)->get("/packages/{$package->id}/edit")
            ->assertSee(__('app.recipe_stage'))
            ->assertSee(__('app.status_drying'));

        $recipe = $package->recipes()->firstOrFail();
        $this->actingAs($admin)->delete("/packages/{$package->id}/recipes/{$recipe->id}")
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $package->recipes()->count());
    }
}
