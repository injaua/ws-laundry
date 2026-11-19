<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceLotsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function product(): InventoryItem
    {
        return InventoryItem::create([
            "kind" => "product",
            "name" => "Detergente azul",
            "type" => "detergent",
            "unit" => "l",
        ]);
    }

    private function purchase(InventoryItem $item, array $overrides = []): void
    {
        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", array_merge([
            "direction" => "in",
            "quantity" => 10,
            "unit_cost" => 2500,
            "reason" => "purchase",
            "moved_at" => now()->toDateString(),
            "lot_number" => "L-001",
            "expires_at" => now()->addDays(365)->toDateString(),
        ], $overrides))->assertSessionHasNoErrors();
    }

    public function test_purchase_entry_creates_a_lot(): void
    {
        $item = $this->product();
        $this->purchase($item);

        $lot = InventoryLot::firstWhere("lot_number", "L-001");
        $this->assertNotNull($lot);
        $this->assertSame($item->id, $lot->inventory_item_id);
        $this->assertSame("10.00", (string) $lot->remaining_qty);
        $this->assertSame($lot->id, InventoryMovement::first()->inventory_lot_id);
    }

    public function test_duplicate_lot_number_for_same_item_is_rejected(): void
    {
        $item = $this->product();
        $this->purchase($item);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "in",
            "quantity" => 5,
            "unit_cost" => 2500,
            "reason" => "purchase",
            "moved_at" => now()->toDateString(),
            "lot_number" => "L-001",
        ])->assertSessionHasErrors("lot_number");
    }

    public function test_exit_consumes_lots_fifo_by_expiry(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 4, "lot_number" => "L-A", "expires_at" => now()->addDays(10)->toDateString()]);
        $this->purchase($item, ["quantity" => 6, "lot_number" => "L-B", "expires_at" => now()->addDays(100)->toDateString()]);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 5,
            "reason" => "consumption",
            "moved_at" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame("0.00", (string) InventoryLot::firstWhere("lot_number", "L-A")->fresh()->remaining_qty);
        $this->assertSame("5.00", (string) InventoryLot::firstWhere("lot_number", "L-B")->fresh()->remaining_qty);
        $this->assertSame("5.00", (string) $item->fresh()->stock_qty);

        $exitMovements = InventoryMovement::where("direction", "out")->orderBy("id")->get();
        $this->assertCount(2, $exitMovements);
        $this->assertSame(
            InventoryLot::firstWhere("lot_number", "L-A")->id,
            $exitMovements[0]->inventory_lot_id
        );
    }

    public function test_expired_lots_are_skipped_by_default(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-EXP", "expires_at" => now()->subDay()->toDateString()]);
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-OK", "expires_at" => now()->addDays(200)->toDateString()]);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 7,
            "reason" => "consumption",
            "moved_at" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        // Only L-OK (5) is allocatable; the remaining 2 falls back to un-lotted legacy stock
        $this->assertSame("5.00", (string) InventoryLot::firstWhere("lot_number", "L-EXP")->fresh()->remaining_qty);
        $this->assertSame("0.00", (string) InventoryLot::firstWhere("lot_number", "L-OK")->fresh()->remaining_qty);
        $this->assertSame("3.00", (string) $item->fresh()->stock_qty);
    }

    public function test_allow_expired_consumes_expired_lot_first(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-EXP", "expires_at" => now()->subDay()->toDateString()]);
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-OK", "expires_at" => now()->addDays(200)->toDateString()]);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 5,
            "reason" => "consumption",
            "moved_at" => now()->toDateString(),
            "allow_expired" => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame("0.00", (string) InventoryLot::firstWhere("lot_number", "L-EXP")->fresh()->remaining_qty);
        $this->assertSame("5.00", (string) InventoryLot::firstWhere("lot_number", "L-OK")->fresh()->remaining_qty);
    }

    public function test_deleting_lot_exit_restores_lot_remaining(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 10, "lot_number" => "L-A"]);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 3,
            "reason" => "breakage",
            "moved_at" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $exit = InventoryMovement::firstWhere("direction", "out");
        $this->actingAs($this->admin())->delete("/resources/movements/{$exit->id}")->assertSessionHasNoErrors();

        $this->assertSame("10.00", (string) InventoryLot::firstWhere("lot_number", "L-A")->fresh()->remaining_qty);
        $this->assertSame("10.00", (string) $item->fresh()->stock_qty);
    }

    public function test_lot_exit_cannot_be_edited(): void
    {
        $item = $this->product();
        $this->purchase($item);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 2,
            "reason" => "consumption",
            "moved_at" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $exit = InventoryMovement::firstWhere("direction", "out");

        $this->actingAs($this->admin())->get("/resources/movements/{$exit->id}/edit")
            ->assertSessionHas("error", __("app.movement_locked_via_lot"));
    }

    public function test_deleting_purchase_already_consumed_is_blocked(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 10, "lot_number" => "L-A"]);

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 4,
            "reason" => "consumption",
            "moved_at" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $entry = InventoryMovement::firstWhere("direction", "in");
        $this->actingAs($this->admin())->delete("/resources/movements/{$entry->id}")
            ->assertSessionHas("error", __("app.movement_revert_conflict"));

        $this->assertSame("6.00", (string) $item->fresh()->stock_qty);
    }

    public function test_lots_page_lists_and_filters_expired(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-EXP", "expires_at" => now()->subDay()->toDateString()]);
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-OK", "expires_at" => now()->addDays(200)->toDateString()]);

        $this->actingAs($this->admin())->get("/resources/lots")
            ->assertOk()
            ->assertSee("L-EXP")
            ->assertSee("L-OK");

        $this->actingAs($this->admin())->get("/resources/lots?status=expired")
            ->assertOk()
            ->assertSee("L-EXP")
            ->assertDontSee("L-OK");
    }

    public function test_dashboard_warns_about_expiring_lots(): void
    {
        $item = $this->product();
        $this->purchase($item, ["quantity" => 5, "lot_number" => "L-SOON", "expires_at" => now()->addDays(10)->toDateString()]);

        $this->actingAs($this->admin())->get("/dashboard")
            ->assertOk()
            ->assertSee(__('app.lots_expiring'), false);
    }
}
