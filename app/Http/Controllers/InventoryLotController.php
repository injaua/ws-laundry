<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use Illuminate\Http\Request;

class InventoryLotController extends Controller
{
    public function index(Request $request)
    {
        $query = InventoryLot::with("item")->where("remaining_qty", ">", 0);

        if ($itemId = $request->item_id) {
            $query->where("inventory_item_id", $itemId);
        }

        if ($kind = $request->kind) {
            $query->whereHas("item", fn ($q) => $q->where("kind", $kind));
        }

        match ($request->status) {
            "expired" => $query->expired(),
            "expiring" => $query->expiringSoon(),
            "ok" => $query->where(fn ($q) => $q->whereNull("expires_at")->orWhereDate("expires_at", ">", now()->addDays(30)->toDateString())),
            default => null,
        };

        $lots = $query->orderBy("expires_at")
            ->orderBy("id")
            ->paginate(20)
            ->withQueryString();

        $items = InventoryItem::whereIn("kind", ["product", "material"])
            ->orderBy("name")
            ->get();

        return view("resources.lots.index", compact("lots", "items"));
    }
}
