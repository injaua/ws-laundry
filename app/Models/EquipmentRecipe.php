<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Products a machine uses on every cycle (e.g. 120 ml of detergent per wash), whatever the weight. */
class EquipmentRecipe extends Model
{
    use BelongsToStore;

    protected $fillable = ["equipment_id", "inventory_item_id", "quantity"];

    protected function casts(): array
    {
        return ["quantity" => "decimal:3"];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }
}
