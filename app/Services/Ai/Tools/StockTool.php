<?php

namespace App\Services\Ai\Tools;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\User;

class StockTool extends AiTool
{
    public function name(): string
    {
        return "stock_status";
    }

    public function description(): string
    {
        return "Stock of products and materials: items below their minimum, lots expiring in the next 30 days or already expired with quantity left, and the stock value.";
    }

    public function ability(): ?string
    {
        return "manage-store";
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        $items = InventoryItem::where("is_active", true)->get();
        $low = $items->filter(fn ($i) => (float) $i->min_stock_qty > 0 && (float) $i->stock_qty <= (float) $i->min_stock_qty);

        $lots = fn ($q) => $q->with("item:id,name,unit")->where("remaining_qty", ">", 0)->orderBy("expires_at")->limit(self::LIMIT)->get()
            ->map(fn (InventoryLot $l) => [
                "item" => $l->item?->name,
                "lot" => $l->lot_number,
                "remaining" => (float) $l->remaining_qty . " " . $l->item?->unit,
                "expires" => self::date($l->expires_at),
            ])->all();

        return [
            "stock_value" => money($items->sum(fn ($i) => (float) $i->stock_qty * (float) $i->unit_cost)),
            "below_minimum" => $low->take(self::LIMIT)->map(fn ($i) => [
                "item" => $i->name,
                "kind" => $i->kind,
                "stock" => (float) $i->stock_qty . " " . $i->unit,
                "minimum" => (float) $i->min_stock_qty . " " . $i->unit,
            ])->values()->all(),
            "expiring_30_days" => $lots(InventoryLot::expiringSoon(30)),
            "expired" => $lots(InventoryLot::expired()),
            "link" => route("resources.items.index"),
        ];
    }
}
