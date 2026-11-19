<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceStockTest extends TestCase
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
            "min_stock_qty" => 5,
        ]);
    }

    private function purchasePayload(array $overrides = []): array
    {
        return array_merge([
            "direction" => "in",
            "quantity" => 10,
            "unit_cost" => 2500,
            "reason" => "purchase",
            "moved_at" => now()->toDateString(),
            "register_expense" => 1,
            "expense_category" => "cleaning_products",
        ], $overrides);
    }

    public function test_cashier_cannot_touch_inventory(): void
    {
        $cashier = User::factory()->cashier()->create();
        $item = $this->product();

        $this->actingAs($cashier)->get("/resources/items/{$item->id}")->assertForbidden();
        $this->actingAs($cashier)->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertForbidden();
    }

    public function test_admin_can_create_item_and_stock_starts_at_zero(): void
    {
        $this->actingAs($this->admin())->post("/resources/items", [
            "kind" => "product",
            "name" => "Lixívia",
            "type" => "bleach",
            "unit" => "l",
            "min_stock_qty" => 2,
        ])->assertRedirect(route("resources.items.index", ["kind" => "product"]))
            ->assertSessionHas("success", __("app.item_created"));

        $item = InventoryItem::firstWhere("name", "Lixívia");
        $this->assertSame("0.00", (string) $item->stock_qty);
    }

    public function test_purchase_entry_creates_stock_expense_and_journal(): void
    {
        $item = $this->product();

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", $this->purchasePayload())
            ->assertSessionHasNoErrors();

        $this->assertSame("10.00", (string) $item->fresh()->stock_qty);
        $this->assertSame("2500.00", (string) $item->fresh()->unit_cost);

        $expense = Expense::firstWhere("source_type", "inventory_movement");
        $this->assertNotNull($expense);
        $this->assertSame("cleaning_products", $expense->category);
        $this->assertSame(25000, (int) $expense->amount);

        // Gross 25000 at 16% -> VAT 3448.28, net 21551.72 (to the cent)
        $entry = JournalEntry::firstWhere("source", "expense");
        $this->assertNotNull($entry);
        $lines = $entry->lines()->get()->keyBy("account_id");
        $this->assertSame("21551.72", $lines[Account::findByCode("6.1.1")->id]->debit);
        $this->assertSame("3448.28", $lines[Account::findByCode("4.4.3.1")->id]->debit);
        $this->assertSame("25000.00", $lines[Account::findByCode("1.1")->id]->credit);
    }

    public function test_editing_purchase_keeps_single_journal_entry(): void
    {
        $item = $this->product();
        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertSessionHasNoErrors();
        $movement = InventoryMovement::firstWhere("inventory_item_id", $item->id);

        $this->actingAs($this->admin())->put("/resources/movements/{$movement->id}", $this->purchasePayload(["unit_cost" => 2600]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, JournalEntry::where("source", "expense")->count());
        $this->assertSame(26000, (int) Expense::firstWhere("source_type", "inventory_movement")->amount);
    }

    public function test_exit_beyond_stock_is_rejected(): void
    {
        $item = $this->product();
        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->post("/resources/items/{$item->id}/movements", [
            "direction" => "out",
            "quantity" => 500,
            "reason" => "consumption",
            "moved_at" => now()->toDateString(),
        ])->assertSessionHas("error", __("app.movement_insufficient_stock"));

        $this->assertSame("10.00", (string) $item->fresh()->stock_qty);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_deleting_purchase_reverts_stock_and_removes_expense(): void
    {
        $item = $this->product();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertSessionHasNoErrors();
        $movement = InventoryMovement::firstWhere("inventory_item_id", $item->id);

        $this->actingAs($admin)->delete("/resources/movements/{$movement->id}")->assertSessionHasNoErrors();

        $this->assertSame("0.00", (string) $item->fresh()->stock_qty);
        $this->assertSame(0, Expense::where("source_type", "inventory_movement")->count());
        $this->assertSame(0, JournalEntry::where("source", "expense")->count());
        $this->assertSame(0, JournalLine::count());
    }

    public function test_item_with_movements_cannot_be_deleted(): void
    {
        $item = $this->product();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertSessionHasNoErrors();

        $this->actingAs($admin)->delete("/resources/items/{$item->id}")
            ->assertSessionHas("error", __("app.item_in_use"));

        $this->assertDatabaseHas("inventory_items", ["id" => $item->id]);
    }

    public function test_switching_reason_away_from_purchase_drops_expense(): void
    {
        $item = $this->product();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertSessionHasNoErrors();
        $movement = InventoryMovement::firstWhere("inventory_item_id", $item->id);

        $this->actingAs($admin)->put("/resources/movements/{$movement->id}", $this->purchasePayload(["reason" => "consumption", "register_expense" => null]))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Expense::where("source_type", "inventory_movement")->count());
        $this->assertSame(0, JournalEntry::where("source", "expense")->count());
        $this->assertSame("10.00", (string) $item->fresh()->stock_qty);
    }

    public function test_deleting_manual_expense_clears_movement_pointer(): void
    {
        $item = $this->product();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/items/{$item->id}/movements", $this->purchasePayload())->assertSessionHasNoErrors();
        $movement = InventoryMovement::firstWhere("inventory_item_id", $item->id);
        $expense = $movement->expense;

        $this->actingAs($admin)->delete("/expenses/{$expense->id}")->assertSessionHasNoErrors();

        $this->assertNull($movement->fresh()->expense_id);
        $this->assertSame("10.00", (string) $item->fresh()->stock_qty);
    }

    public function test_low_stock_filter_and_dashboard_alert(): void
    {
        $item = $this->product(); // min 5
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/items/{$item->id}/movements", $this->purchasePayload(["quantity" => 4, "register_expense" => null]))->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/resources/items?kind=product&low_stock=1")
            ->assertOk()
            ->assertSee("Detergente azul");

        $this->actingAs($admin)->get("/dashboard")
            ->assertOk()
            ->assertSee(__('app.low_stock_items'), false);

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get("/dashboard")
            ->assertOk()
            ->assertDontSee(__('app.low_stock_items'), false);
    }
}
