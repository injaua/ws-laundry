<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRecipe extends Model
{
    use BelongsToStore;
    use HasFactory;

    public const BASES = ["per_kg", "per_piece"];

    /** Processing stages a recipe can consume at (received is not a work stage). */
    public const STAGES = ["washing", "drying", "ironing", "folding", "ready", "delivering", "completed"];

    protected $fillable = ["service_id", "inventory_item_id", "quantity", "basis", "stage"];

    protected $attributes = [
        "basis" => "per_kg",
        "stage" => "washing",
    ];

    protected function casts(): array
    {
        return [
            "quantity" => "decimal:2",
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }
}
