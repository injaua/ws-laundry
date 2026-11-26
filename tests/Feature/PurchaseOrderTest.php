<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function supplier(): Supplier
    {
        return Supplier::create(["name" => "Distribuidora Matola", "phone" => "867314502"]);
    }

    private function item(): InventoryItem
    {
        return InventoryItem::create([
            "kind" => "product",
            "name" => "Detergente azul",
            "type" => "detergent",
            "unit" => "l",
        ]);
    }

    private function makePo(?Supplier $supplier = null, ?InventoryItem $item = null, float $qty = 10, int $cost = 2500): PurchaseOrder
    {
        $supplier ??= $this->supplier();
        $item ??= $this->item();

        $this->actingAs($this->admin())->post("/resources/purchases", [
            "supplier_id" => $supplier->id,
            "order_date" => now()->toDateString(),
            "lines" => [
                ["inventory_item_id" => $item->id, "quantity" => $qty, "unit_cost" => $cost],
            ],
        ])->assertSessionHasNoErrors();

        return PurchaseOrder::latest("id")->first();
    }

    public function test_cashier_cannot_access_purchasing(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/resources/suppliers")->assertForbidden();
        $this->actingAs($cashier)->get("/resources/purchases")->assertForbidden();
    }

    public function test_draft_is_created_with_number_total_and_lines(): void
    {
        $po = $this->makePo(qty: 4, cost: 2500);

        $this->assertMatchesRegularExpression("/^OC-\d{6}$/", $po->po_number);
        $this->assertSame("draft", $po->status);
        $this->assertSame(10000, (int) $po->total);
        $this->assertSame(1, $po->items()->count());
    }

    public function test_duplicate_lines_are_rejected(): void
    {
        $supplier = $this->supplier();
        $item = $this->item();

        $this->actingAs($this->admin())->post("/resources/purchases", [
            "supplier_id" => $supplier->id,
            "order_date" => now()->toDateString(),
            "lines" => [
                ["inventory_item_id" => $item->id, "quantity" => 2, "unit_cost" => 100],
                ["inventory_item_id" => $item->id, "quantity" => 3, "unit_cost" => 100],
            ],
        ])->assertSessionHasErrors("lines");
    }

    public function test_only_draft_can_be_edited_or_cancelled(): void
    {
        $po = $this->makePo();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order")->assertSessionHasNoErrors();
        $this->assertSame("ordered", $po->fresh()->status);

        $this->actingAs($admin)->get("/resources/purchases/{$po->id}/edit")
            ->assertSessionHas("error", __("app.po_locked"));
    }

    public function test_receipt_creates_movement_lot_expense_and_journal(): void
    {
        $item = $this->item();
        $po = $this->makePo(item: $item, qty: 10, cost: 2500);
        $admin = $this->admin();
        $line = $po->items()->first();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order")->assertSessionHasNoErrors();

        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 4,
            "lot_number" => "L-PO1",
            "expires_at" => now()->addDays(365)->toDateString(),
            "register_expense" => 1,
        ])->assertSessionHasNoErrors();

        $po->refresh();
        $this->assertSame("partial", $po->status);
        $this->assertSame("4.00", (string) $item->fresh()->stock_qty);
        $this->assertSame("4.00", (string) $line->fresh()->received_qty);

        $lot = InventoryLot::firstWhere("lot_number", "L-PO1");
        $this->assertNotNull($lot);
        $this->assertSame("4.00", (string) $lot->remaining_qty);

        $movement = InventoryMovement::firstWhere("source_type", "purchase_order");
        $this->assertNotNull($movement);
        $this->assertSame($po->id, $movement->source_id);
        $this->assertSame($lot->id, $movement->inventory_lot_id);

        $expense = Expense::firstWhere("source_type", "inventory_movement");
        $this->assertNotNull($expense);
        $this->assertSame(10000, (int) $expense->amount);
        $this->assertNotNull(JournalEntry::firstWhere("source", "expense"));
    }

    public function test_receiving_everything_marks_received_and_blocks_over_receive(): void
    {
        $item = $this->item();
        $po = $this->makePo(item: $item, qty: 5, cost: 100);
        $line = $po->items()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order");
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame("received", $po->fresh()->status);

        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 1,
        ])->assertSessionHasErrors("quantity");
    }

    public function test_receipt_over_pending_is_rejected(): void
    {
        $po = $this->makePo(qty: 3, cost: 100);
        $line = $po->items()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order");

        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 10,
        ])->assertSessionHasErrors("quantity");

        $this->assertSame("ordered", $po->fresh()->status);
        $this->assertSame("0.00", (string) $line->fresh()->received_qty);
    }

    public function test_cannot_cancel_after_partial_receipt(): void
    {
        $po = $this->makePo(qty: 10, cost: 100);
        $line = $po->items()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order");
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", ["quantity" => 2]);

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/cancel")
            ->assertSessionHas("errors");
        $this->assertSame("partial", $po->fresh()->status);
    }

    public function test_received_order_cannot_be_deleted_but_draft_can(): void
    {
        $po = $this->makePo(qty: 10, cost: 100);
        $line = $po->items()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order");
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", ["quantity" => 1]);

        $this->actingAs($admin)->delete("/resources/purchases/{$po->id}")
            ->assertSessionHas("error", __("app.po_in_use"));

        $draft = $this->makePo();
        $this->actingAs($admin)->delete("/resources/purchases/{$draft->id}")
            ->assertSessionHas("success");
    }

    public function test_supplier_with_orders_cannot_be_deleted(): void
    {
        $supplier = $this->supplier();
        $this->makePo(supplier: $supplier);

        $this->actingAs($this->admin())->delete("/resources/suppliers/{$supplier->id}")
            ->assertSessionHas("error", __("app.supplier_in_use"));

        $this->assertDatabaseHas("suppliers", ["id" => $supplier->id]);
    }

    public function test_receipt_with_duplicate_lot_number_is_rejected(): void
    {
        $item = $this->item();
        $po = $this->makePo(item: $item, qty: 10, cost: 100);
        $line = $po->items()->first();
        $admin = $this->admin();

        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order");
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 2,
            "lot_number" => "L-SAME",
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 1,
            "lot_number" => "L-SAME",
        ])->assertSessionHasErrors("lot_number");
    }

    public function test_receive_form_prefills_quantity_cost_and_lot_from_line(): void
    {
        $po = $this->makePo(qty: 10, cost: 2500);
        $admin = $this->admin();
        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/order");
        $line = $po->items()->first();

        $this->actingAs($admin)->get("/resources/purchases/{$po->id}")
            ->assertOk()
            ->assertSee('data-qty="10"', false)
            ->assertSee('data-cost="2500"', false)
            ->assertSee('data-lot="'.$po->po_number.'-1"', false)
            ->assertSee('name="lot_number" id="receive-lot" class="form-control form-control-sm " value="'.$po->po_number.'-1"', false);

        // A second receipt on the same line suggests a lot number not yet taken.
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$line->id}/receive", [
            "quantity" => 4,
            "lot_number" => $po->po_number."-1",
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/resources/purchases/{$po->id}")
            ->assertSee('data-qty="6"', false)
            ->assertSee('data-lot="'.$po->po_number.'-1-2"', false);
    }

    private function expenseCategory(): ExpenseCategory
    {
        return ExpenseCategory::firstOrCreate(["key" => "other"], ["name" => "Outros", "color" => "secondary"]);
    }

    public function test_expense_can_raise_purchase_order_creating_missing_resources(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();
        $existing = $this->item();
        $this->expenseCategory();

        $this->actingAs($admin)->post("/expenses", [
            "title" => "Compra mensal",
            "category" => "other",
            "amount" => 9500,
            "vat_rate" => 0,
            "expense_date" => now()->toDateString(),
            "create_purchase_order" => 1,
            "po_supplier_id" => $supplier->id,
            "po_lines" => [
                ["kind" => "product", "name" => "detergente AZUL", "unit" => "kg", "quantity" => 2, "unit_cost" => 500],
                ["kind" => "material", "name" => "Cabides de arame", "unit" => "un", "quantity" => 100, "unit_cost" => 15],
                ["kind" => "equipment", "name" => "Máquina de lavar 12kg", "quantity" => 1, "unit_cost" => 7000],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $expense = Expense::firstWhere("title", "Compra mensal");
        $po = PurchaseOrder::firstWhere("expense_id", $expense->id);
        $this->assertNotNull($po);
        $this->assertSame("ordered", $po->status);
        $this->assertSame(3, $po->items()->count());
        $this->assertEquals(9500, (float) $po->total);

        // The existing product is reused; the material and the equipment are created.
        $this->assertSame(1, InventoryItem::where("kind", "product")->count());
        $this->assertTrue($po->items()->where("inventory_item_id", $existing->id)->exists());
        $material = InventoryItem::where("kind", "material")->firstWhere("name", "Cabides de arame");
        $this->assertNotNull($material);
        $this->assertSame("un", $material->unit);
        $equipment = Equipment::firstWhere("name", "Máquina de lavar 12kg");
        $this->assertSame(Equipment::AWAITING_DELIVERY, $equipment->status);

        // Receiving does not record the cost again: only the original expense exists.
        $productLine = $po->items()->where("inventory_item_id", $existing->id)->first();
        $equipmentLine = $po->items()->whereNotNull("equipment_id")->first();
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$productLine->id}/receive", [
            "quantity" => 2,
            "register_expense" => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post("/resources/purchases/{$po->id}/items/{$equipmentLine->id}/receive", [
            "quantity" => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Expense::count());
        $this->assertEquals(2, (float) $existing->fresh()->stock_qty);
        $this->assertSame("operational", $equipment->fresh()->status);
        $this->assertSame("partial", $po->fresh()->status);
    }

    public function test_cancelling_expense_order_removes_undelivered_equipment(): void
    {
        $admin = $this->admin();
        $supplier = $this->supplier();
        $this->expenseCategory();

        $this->actingAs($admin)->post("/expenses", [
            "title" => "Ferro industrial",
            "category" => "other",
            "amount" => 3000,
            "vat_rate" => 0,
            "expense_date" => now()->toDateString(),
            "create_purchase_order" => 1,
            "po_supplier_id" => $supplier->id,
            "po_lines" => [["kind" => "equipment", "name" => "Ferro industrial", "quantity" => 1, "unit_cost" => 3000]],
        ])->assertSessionHasNoErrors();

        $po = PurchaseOrder::latest("id")->first();
        $this->actingAs($admin)->patch("/resources/purchases/{$po->id}/cancel")->assertSessionHasNoErrors();

        $this->assertSame("cancelled", $po->fresh()->status);
        $this->assertSame(0, Equipment::count());
    }

    public function test_expense_purchase_order_requires_supplier_and_lines(): void
    {
        $this->expenseCategory();

        $this->actingAs($this->admin())->post("/expenses", [
            "title" => "Sem linhas",
            "category" => "other",
            "amount" => 100,
            "vat_rate" => 0,
            "expense_date" => now()->toDateString(),
            "create_purchase_order" => 1,
        ])->assertSessionHasErrors(["po_supplier_id", "po_lines"]);

        $this->assertSame(0, Expense::count());
    }
}
