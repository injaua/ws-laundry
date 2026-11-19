<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PackageRecipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderConsumption
{
    /**
     * Trigger point for every place that can advance an order status:
     * consume the recipes of each processing stage the order has newly reached.
     * A forward jump consumes every skipped stage at once; each stage is only
     * ever consumed once (tracked in orders.consumed_stages).
     */
    public function consumeIfNeeded(Order $order, User $user): ?array
    {
        if (!$order->wasChanged("status")) {
            return null;
        }

        $pending = $this->pendingStages($order);

        if ($pending === []) {
            return null;
        }

        return $this->consume($order, $user, $pending);
    }

    /** Processing stages at or before the current status that were not consumed yet. */
    public function pendingStages(Order $order): array
    {
        $passed = array_slice(Order::STATUSES, 1, $order->statusRank());

        return array_values(array_diff($passed, $order->consumed_stages ?? []));
    }

    public function shortfallMessage(?array $result): ?string
    {
        if (!$result || empty($result["shortfalls"])) {
            return null;
        }

        $names = collect($result["shortfalls"])
            ->map(fn ($qty, $itemId) => InventoryItem::find($itemId)?->name." (−".$qty.")")
            ->filter()
            ->implode(", ");

        return $names === "" ? null : __("app.consumption_shortfall", ["items" => $names]);
    }

    /**
     * Deduct the recipe materials of the given stages for an order.
     * Stock is never reverted when orders are edited or deleted: the work
     * already happened physically.
     *
     * @param  string[]  $stages
     * @return array{consumed: array<int, float>, shortfalls: array<int, float>} keyed by inventory item id
     */
    public function consume(Order $order, User $user, array $stages): array
    {
        $consumed = [];
        $shortfalls = [];

        DB::transaction(function () use ($order, $user, $stages, &$consumed, &$shortfalls) {
            foreach ($stages as $stage) {
                foreach ($this->aggregateNeeds($order, $stage) as $itemId => $qty) {
                    $item = InventoryItem::find($itemId);
                    if (!$item || !$item->is_active) {
                        continue;
                    }

                    $result = InventoryMovement::consume($item, $qty, [
                        "direction" => "out",
                        "unit_cost" => null,
                        "reason" => "consumption",
                        "user_id" => $user->id,
                        "source_type" => "order",
                        "source_id" => $order->id,
                        "moved_at" => today()->toDateString(),
                        "notes" => __("app.order_consumption_note", [
                            "number" => $order->order_number,
                            "stage" => __("app.status_".$stage),
                        ]),
                    ]);

                    if ($result["consumed"] > 0) {
                        $consumed[$itemId] = round(($consumed[$itemId] ?? 0) + $result["consumed"], 2);
                    }
                    if ($result["shortfall"] > 0) {
                        $shortfalls[$itemId] = round(($shortfalls[$itemId] ?? 0) + $result["shortfall"], 2);
                    }
                }
            }

            $order->update([
                "consumed_stages" => array_values(array_unique([...($order->consumed_stages ?? []), ...$stages])),
                "materials_consumed_at" => now(),
            ]);
        });

        return ["consumed" => $consumed, "shortfalls" => $shortfalls];
    }

    /** @return array<int, float> required quantity keyed by inventory item id */
    public function aggregateNeeds(Order $order, ?string $stage = null): array
    {
        $order->loadMissing(["items.service.recipes.item", "items.package.recipes.item"]);

        $needs = [];

        foreach ($order->items as $line) {
            $kg = 0.0;
            $pieces = 0.0;
            $packages = 0.0;

            switch ($line->type) {
                case "by_weight":
                    $kg = (float) $line->weight;
                    break;
                case "by_piece":
                    $pieces = (float) $line->amount;
                    break;
                case "by_package":
                    $kg = (float) ($line->package->weight_kg ?? 0) * (int) $line->amount;
                    $packages = (float) $line->amount;
                    break;
            }

            $isPackageLine = $line->type === "by_package";
            $recipes = collect($line->service?->recipes ?? []);
            if ($isPackageLine) {
                $recipes = $recipes->concat($line->package?->recipes ?? []);
            }

            foreach ($recipes as $recipe) {
                if ($stage !== null && $recipe->stage !== $stage) {
                    continue;
                }

                // Package recipes count "pieces" as package units; service ones as clothing pieces.
                $unitFactor = $recipe instanceof PackageRecipe ? $packages : $pieces;
                $factor = $recipe->basis === "per_kg" ? $kg : $unitFactor;
                $qty = round((float) $recipe->quantity * $factor, 2);

                if ($qty <= 0) {
                    continue;
                }

                $needs[$recipe->inventory_item_id] = round(($needs[$recipe->inventory_item_id] ?? 0) + $qty, 2);
            }
        }

        return $needs;
    }
}
