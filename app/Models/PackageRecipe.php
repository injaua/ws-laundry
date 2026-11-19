<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageRecipe extends Model
{
    use BelongsToStore;
    use HasFactory;

    public const BASES = ServiceRecipe::BASES;

    public const STAGES = ServiceRecipe::STAGES;

    protected $fillable = ["package_id", "inventory_item_id", "quantity", "basis", "stage"];

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

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }
}
