<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        "purchase_order_id", "inventory_item_id", "equipment_id", "quantity", "unit_cost", "subtotal", "received_qty",
    ];

    protected function casts(): array
    {
        return [
            "quantity" => "decimal:2",
            "unit_cost" => "decimal:2",
            "subtotal" => "decimal:2",
            "received_qty" => "decimal:2",
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function isEquipment(): bool
    {
        return $this->equipment_id !== null;
    }

    public function pendingQty(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_qty);
    }

    /** Name of what the line orders: the stock item or the equipment. */
    public function displayName(): string
    {
        return $this->item?->name ?? $this->equipment?->name ?? "—";
    }

    /** Unit label of the line (equipment is counted in units). */
    public function unitLabel(): string
    {
        return __("app.unit_".($this->item?->unit ?? "un"));
    }

    /**
     * Lot number suggested when receiving this line: the order number and the line's
     * position ("OC-000004-1"), with a suffix when that lot already exists for the item
     * (a second partial receipt gets "OC-000004-1-2").
     */
    public function suggestedLotNumber(int $position): string
    {
        $base = $this->purchaseOrder->po_number."-".$position;

        if (!$this->inventory_item_id) {
            return $base;
        }

        $taken = InventoryLot::where("inventory_item_id", $this->inventory_item_id)
            ->where("lot_number", "like", $base."%")
            ->pluck("lot_number")
            ->all();

        $candidate = $base;
        for ($n = 2; in_array($candidate, $taken, true); $n++) {
            $candidate = $base."-".$n;
        }

        return $candidate;
    }
}
