<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class InventoryMovementController extends Controller
{
    public const FORM_REASONS = ["purchase", "consumption", "breakage", "adjustment", "return"];

    public function store(Request $request, InventoryItem $item)
    {
        $data = $this->validated($request);
        $movementData = Arr::except($data, ["register_expense", "expense_category"]);

        if ($movementData["direction"] === "out" && (float) $movementData["quantity"] > (float) $item->stock_qty) {
            return back()->withInput()->with("error", __('app.movement_insufficient_stock'));
        }

        $movementData["user_id"] = $request->user()->id;

        InventoryMovement::record(
            $item,
            $movementData,
            (bool) ($data["register_expense"] ?? false),
            $data["expense_category"] ?? $this->defaultCategory($item->kind),
        );

        return redirect()->route("resources.items.show", $item)
            ->with("success", __('app.movement_created'));
    }

    public function edit(InventoryMovement $movement)
    {
        $movement->load("item");
        $categories = ExpenseCategory::orderBy("name")->get();

        return view("resources.items.movements_edit", compact("movement", "categories"));
    }

    public function update(Request $request, InventoryMovement $movement)
    {
        if ($movement->reason === "maintenance") {
            return redirect()->route("resources.items.show", $movement->inventory_item_id)
                ->with("error", __('app.movement_locked_via_job'));
        }

        $data = $this->validated($request);
        $movementData = Arr::except($data, ["register_expense", "expense_category"]);

        $stock = (float) $movement->item->stock_qty;
        $available = $stock - $movement->stockDelta();
        $newDelta = ($movementData["direction"] === "in" ? 1 : -1) * (float) $movementData["quantity"];

        if ($available + $newDelta < 0) {
            return back()->withInput()->with("error", __('app.movement_insufficient_stock'));
        }

        $movement->resync(
            $movementData,
            (bool) ($data["register_expense"] ?? false),
            $data["expense_category"] ?? $this->defaultCategory($movement->item->kind),
        );

        return redirect()->route("resources.items.show", $movement->inventory_item_id)
            ->with("success", __('app.movement_updated'));
    }

    public function destroy(InventoryMovement $movement)
    {
        $itemId = $movement->inventory_item_id;

        if ($movement->reason === "maintenance") {
            return redirect()->route("resources.items.show", $itemId)
                ->with("error", __('app.movement_locked_via_job'));
        }

        if ($movement->direction === "in") {
            $after = (float) $movement->item->stock_qty - $movement->stockDelta();
            if ($after < 0) {
                return redirect()->route("resources.items.show", $itemId)
                    ->with("error", __('app.movement_revert_conflict'));
            }
        }

        $movement->remove();

        return redirect()->route("resources.items.show", $itemId)
            ->with("success", __('app.movement_deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            "direction" => "required|in:in,out",
            "quantity" => "required|numeric|min:0.01",
            "unit_cost" => "nullable|numeric|min:0|required_if:direction,in",
            "reason" => "required|in:".implode(",", self::FORM_REASONS),
            "moved_at" => "required|date",
            "notes" => "nullable|string|max:1000",
            "register_expense" => "nullable|boolean",
            "expense_category" => "nullable|required_if:register_expense,1|exists:expense_categories,key",
        ]);
    }

    private function defaultCategory(string $kind): string
    {
        return $kind === "product" ? "produtos" : "lainnya";
    }
}
