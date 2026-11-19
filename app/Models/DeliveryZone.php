<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Area served by the couriers, with the fee charged per trip (pickup or delivery). */
class DeliveryZone extends Model
{
    use BelongsToStore;
    use Loggable;

    protected $fillable = ["name", "description", "fee", "free_from", "is_active", "sort_order"];

    protected function casts(): array
    {
        return [
            "fee" => "decimal:2",
            "free_from" => "decimal:2",
            "is_active" => "boolean",
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(CourierJob::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where("is_active", true)->orderBy("sort_order")->orderBy("name");
    }

    /** Fee for one trip; free when the order (before VAT) reaches the zone threshold. */
    public function feeFor(?float $orderAmount = null): float
    {
        if ($this->free_from !== null && $orderAmount !== null && $orderAmount >= (float) $this->free_from) {
            return 0.0;
        }

        return (float) $this->fee;
    }

    public function feeLabel(): string
    {
        $fee = (float) $this->fee > 0 ? money((float) $this->fee) : __("app.free");

        return $this->free_from !== null && (float) $this->fee > 0
            ? $fee . " · " . __("app.free_from_amount", ["amount" => money((float) $this->free_from)])
            : $fee;
    }
}
