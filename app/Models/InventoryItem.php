<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory, Loggable;

    public const KINDS = ["product", "material", "part"];

    public const TYPES = [
        "product" => ["detergent", "soap", "softener", "bleach", "disinfectant", "other"],
        "material" => ["hanger", "bag", "packaging", "ribbon", "other"],
        "part" => ["filter", "belt", "hose", "pump", "electronic_board", "other"],
    ];

    public const UNITS = ["un", "kg", "l"];

    protected $fillable = [
        "kind", "name", "type", "unit", "stock_qty", "min_stock_qty",
        "unit_cost", "supplier", "is_active", "notes",
    ];

    protected function casts(): array
    {
        return [
            "stock_qty" => "decimal:2",
            "min_stock_qty" => "decimal:2",
            "unit_cost" => "decimal:0",
            "is_active" => "boolean",
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function jobItems(): HasMany
    {
        return $this->hasMany(MaintenanceJobItem::class);
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where("kind", $kind);
    }

    public function isLowStock(): bool
    {
        return (float) $this->min_stock_qty > 0 && (float) $this->stock_qty <= (float) $this->min_stock_qty;
    }
}
