<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        "purchase_order_id", "inventory_item_id", "quantity", "unit_cost", "subtotal", "received_qty",
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

    public function pendingQty(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_qty);
    }
}
