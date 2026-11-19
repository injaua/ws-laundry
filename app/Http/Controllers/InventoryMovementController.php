<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class InventoryMovementController extends Controller
{
    public const FORM_REASONS = ["purchase", "consumption", "breakage", "adjustment", "return"];

    public function store(Request $request, InventoryItem $item)
    {
        $data = $this->validated($request);
        $movementData = Arr::only($data, ["direction", "quantity", "unit_cost", "reason", "moved_at", "notes"]);

        if (!empty($data["register_expense"])) {
            AccountingPeriod::assertOpen($movementData["moved_at"], "moved_at");
        }

        if ($movementData["direction"] === "out") {
            if ((float) $movementData["quantity"] > (float) $item->stock_qty) {
                return back()->withInput()->with("error", __('app.movement_insufficient_stock'));
            }

            $result = InventoryMovement::consume(
                $item,
                (float) $movementData["quantity"],
                $movementData + ["user_id" => $request->user()->id],
                (bool) ($data["allow_expired"] ?? false),
            );

            if ((bool) ($data["allow_expired"] ?? false) && $this->usedExpiredLot($result["movements"])) {
                return redirect()->route("resources.items.show", $item)
                    ->with("warning", __('app.movement_expired_lots'));
            }

            return redirect()->route("resources.items.show", $item)
                ->with("success", __('app.movement_created'));
        }

        if (!empty($data["lot_number"]) && InventoryLot::where("inventory_item_id", $item->id)
            ->where("lot_number", $data["lot_number"])->exists()) {
            return back()->withInput()
                ->withErrors(["lot_number" => __('app.lot_number_taken')]);
        }

        $movement = InventoryMovement::record(
            $item,
            $movementData + ["user_id" => $request->user()->id],
            (bool) ($data["register_expense"] ?? false),
            $data["expense_category"] ?? $this->defaultCategory($item->kind),
            Arr::only($data, ["lot_number", "expires_at"]),
        );

        return redirect()->route("resources.items.show", $item)
            ->with("success", __('app.movement_created'));
    }

    public function edit(InventoryMovement $movement)
    {
        if ($movement->isLocked() || $movement->inventory_lot_id !== null) {
            return redirect()->route("resources.items.show", $movement->inventory_item_id)
                ->with("error", $movement->isLocked() ? __('app.movement_locked_via_job') : __('app.movement_locked_via_lot'));
        }

        $movement->load("item");
        $categories = ExpenseCategory::orderBy("name")->get();

        return view("resources.items.movements_edit", compact("movement", "categories"));
    }

    public function update(Request $request, InventoryMovement $movement)
    {
        if ($movement->isLocked() || $movement->inventory_lot_id !== null) {
            return redirect()->route("resources.items.show", $movement->inventory_item_id)
                ->with("error", $movement->isLocked() ? __('app.movement_locked_via_job') : __('app.movement_locked_via_lot'));
        }

        $data = $this->validated($request);
        $movementData = Arr::except($data, ["register_expense", "expense_category", "allow_expired", "lot_number", "expires_at"]);

        if ($movement->expense_id || !empty($data["register_expense"])) {
            AccountingPeriod::assertOpen($movement->moved_at, "moved_at");
            AccountingPeriod::assertOpen($movementData["moved_at"], "moved_at");
        }

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

        if ($movement->expense_id && $period = AccountingPeriod::closedOn($movement->moved_at)) {
            return redirect()->route("resources.items.show", $itemId)
                ->with("error", __("app.period_closed", ["period" => $period->label()]));
        }

        if ($movement->isLocked()) {
            return redirect()->route("resources.items.show", $itemId)
                ->with("error", __('app.movement_locked_via_job'));
        }

        if ($movement->direction === "in") {
            $after = (float) $movement->item->stock_qty - $movement->stockDelta();
            if ($after < 0 || $movement->wouldBreakLot()) {
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
            "lot_number" => "nullable|string|max:60",
            "expires_at" => "nullable|date",
            "allow_expired" => "nullable|boolean",
            "register_expense" => "nullable|boolean",
            "expense_category" => "nullable|required_if:register_expense,1|exists:expense_categories,key",
        ]);
    }

    /** True when an outbound had to reach expired lots (only possible with allow_expired). */
    private function usedExpiredLot(array $movements): bool
    {
        return collect($movements)->contains(
            fn (InventoryMovement $m) => $m->inventory_lot_id && $m->lot->isExpired()
        );
    }

    private function defaultCategory(string $kind): string
    {
        return $kind === "product" ? "cleaning_products" : "other";
    }
}
