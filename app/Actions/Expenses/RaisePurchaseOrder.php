<?php

namespace App\Actions\Expenses;

use App\Models\Equipment;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Money;

/**
 * Raises the purchase order of an expense: the purchase is paid, the goods are still to
 * arrive, so the order is created as "ordered" (awaiting receipt) and linked to the
 * expense — receiving it adds stock and lots but never records the cost a second time.
 *
 * Each line names a resource of the resources module by kind: a cleaning product, a
 * material/consumable or a part reuses the stock item of the same name, or creates it;
 * equipment is always a new asset, registered as "awaiting delivery" until it arrives.
 */
class RaisePurchaseOrder
{
    /** Kinds a line may order: the stock kinds plus equipment. */
    public const KINDS = [...InventoryItem::KINDS, "equipment"];

    /**
     * @param  array<int, array{kind: string, name: string, unit?: ?string, quantity: float|string, unit_cost: float|string}>  $lines
     */
    public function handle(Expense $expense, Supplier $supplier, array $lines, User $user): PurchaseOrder
    {
        $order = PurchaseOrder::draft([
            "supplier_id" => $supplier->id,
            "user_id" => $user->id,
            "expense_id" => $expense->id,
            "order_date" => $expense->expense_date->toDateString(),
            "notes" => __("app.po_from_expense_note", ["title" => $expense->title]),
        ]);

        foreach ($this->mergeDuplicates($lines) as $line) {
            $cost = Money::round($line["unit_cost"]);
            $quantity = (float) $line["quantity"];

            if ($line["kind"] === "equipment") {
                // One asset per unit ordered, each on its own line.
                for ($i = 0; $i < (int) $quantity; $i++) {
                    $order->items()->create([
                        "equipment_id" => $this->newEquipment($line["name"], $cost, $supplier)->id,
                        "quantity" => 1,
                        "unit_cost" => $cost,
                        "subtotal" => $cost,
                    ]);
                }

                continue;
            }

            $order->items()->create([
                "inventory_item_id" => $this->stockItem($line, $cost, $supplier)->id,
                "quantity" => $quantity,
                "unit_cost" => $cost,
                "subtotal" => Money::mul($cost, $quantity),
            ]);
        }

        $order->recalcTotal();
        $order->update(["status" => "ordered"]);

        return $order;
    }

    /** The stock item of that kind and name (case-insensitive), created when missing. */
    private function stockItem(array $line, float $cost, Supplier $supplier): InventoryItem
    {
        $name = trim($line["name"]);

        $existing = InventoryItem::where("kind", $line["kind"])
            ->whereRaw("LOWER(name) = ?", [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return InventoryItem::create([
            "kind" => $line["kind"],
            "name" => $name,
            "type" => "other",
            "unit" => in_array($line["unit"] ?? null, InventoryItem::UNITS, true) ? $line["unit"] : "un",
            "stock_qty" => 0,
            "min_stock_qty" => 0,
            "unit_cost" => $cost,
            "supplier" => $supplier->name,
            "is_active" => true,
        ]);
    }

    private function newEquipment(string $name, float $cost, Supplier $supplier): Equipment
    {
        return Equipment::create([
            "name" => trim($name),
            "type" => "other",
            "purchase_price" => $cost,
            "supplier" => $supplier->name,
            "status" => Equipment::AWAITING_DELIVERY,
        ]);
    }

    /**
     * A stock item may appear on one line of an order only: lines naming the same item
     * are merged (quantities added, the weighted unit cost kept).
     */
    private function mergeDuplicates(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            if ($line["kind"] === "equipment") {
                $merged[] = $line;

                continue;
            }

            $key = $line["kind"]."|".mb_strtolower(trim($line["name"]));

            if (!isset($merged[$key])) {
                $merged[$key] = $line;

                continue;
            }

            $qty = (float) $merged[$key]["quantity"] + (float) $line["quantity"];
            $total = Money::add(Money::mul($merged[$key]["unit_cost"], $merged[$key]["quantity"]), Money::mul($line["unit_cost"], $line["quantity"]));
            $merged[$key]["quantity"] = $qty;
            $merged[$key]["unit_cost"] = $qty > 0 ? Money::div($total, $qty) : 0;
        }

        return array_values($merged);
    }
}
