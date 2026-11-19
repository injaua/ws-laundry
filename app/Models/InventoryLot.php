<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLot extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable;

    protected $fillable = [
        "inventory_item_id", "lot_number", "quantity", "remaining_qty",
        "unit_cost", "expires_at", "received_at", "notes",
    ];

    protected function casts(): array
    {
        return [
            "quantity" => "decimal:2",
            "remaining_qty" => "decimal:2",
            "unit_cost" => "decimal:0",
            "expires_at" => "date",
            "received_at" => "date",
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, "inventory_item_id");
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where("remaining_qty", ">", 0);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull("expires_at")->whereDate("expires_at", "<", today()->toDateString());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull("expires_at")
            ->whereDate("expires_at", ">=", today()->toDateString())
            ->whereDate("expires_at", "<=", now()->addDays($days)->toDateString());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isBefore(today());
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expires_at !== null
            && !$this->isExpired()
            && $this->expires_at->lte(now()->addDays($days));
    }

    public function getStatusColorAttribute(): string
    {
        if ($this->isExpired()) {
            return "danger";
        }

        return $this->isExpiringSoon() ? "warning" : "success";
    }

    /**
     * FIFO allocation across the item's open lots: earliest expiry first,
     * lots without expiry last, expired lots skipped unless allowed.
     *
     * @return array<int, array{lot: InventoryLot, qty: float}>
     */
    public static function allocate(int $itemId, float $qty, bool $allowExpired = false): array
    {
        if ($qty <= 0) {
            return [];
        }

        $query = static::where("inventory_item_id", $itemId)
            ->where("remaining_qty", ">", 0)
            ->lockForUpdate()
            ->orderByRaw("(expires_at is null) asc, expires_at asc, id asc");

        if (!$allowExpired) {
            $query->where(function (Builder $q) {
                $q->whereNull("expires_at")->orWhereDate("expires_at", ">=", today()->toDateString());
            });
        }

        $allocations = [];
        $left = round($qty, 2);

        foreach ($query->get() as $lot) {
            if ($left <= 0.001) {
                break;
            }

            $take = min($left, (float) $lot->remaining_qty);
            $lot->consume($take);
            $allocations[] = ["lot" => $lot, "qty" => $take];
            $left = round($left - $take, 2);
        }

        return $allocations;
    }

    public function consume(float $qty): void
    {
        $this->remaining_qty = (float) $this->remaining_qty - $qty;
        $this->save();
    }

    public function restore(float $qty): void
    {
        $this->remaining_qty = (float) $this->remaining_qty + $qty;
        $this->save();
    }
}
