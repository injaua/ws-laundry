<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Time-limited discount on catalog prices. A fixed value is taken off the unit
 * price, so it means "per kg" on weight services, "per piece" on piece services
 * and "per package" on packages. Promotions never stack with each other or with a
 * customer's negotiated price/discount: the lowest resulting price wins.
 */
class Promotion extends Model
{
    use BelongsToStore;
    use Loggable;

    public const TYPES = ["percent", "fixed"];

    public const SCOPES = ["all", "selected"];

    protected $fillable = [
        "name", "description", "discount_type", "value", "applies_to",
        "starts_on", "ends_on", "weekdays", "is_active", "created_by",
    ];

    protected function casts(): array
    {
        return [
            "value" => "decimal:2",
            "starts_on" => "date",
            "ends_on" => "date",
            "weekdays" => "array",
            "is_active" => "boolean",
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isRunningOn(CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        return $this->is_active
            && $this->starts_on->toDateString() <= $day
            && ($this->ends_on === null || $this->ends_on->toDateString() >= $day)
            && (empty($this->weekdays) || in_array($date->dayOfWeek, array_map("intval", $this->weekdays), true));
    }

    public function appliesTo(Service|Package $item): bool
    {
        if ($this->applies_to === "all") {
            return true;
        }

        $column = $item instanceof Service ? "service_id" : "package_id";

        return $this->items->contains(fn (PromotionItem $row) => (int) $row->{$column} === (int) $item->id);
    }

    /** Price after this promotion, never below zero. */
    public function discountedPrice(float $base): float
    {
        $price = $this->discount_type === "percent"
            ? round($base * (100 - (float) $this->value) / 100, 2)
            : round($base - (float) $this->value, 2);

        return max(0.0, $price);
    }

    /** active, scheduled, expired or inactive — for the list. */
    public function status(): string
    {
        return match (true) {
            !$this->is_active => "inactive",
            $this->ends_on !== null && $this->ends_on->lt(today()) => "expired",
            $this->starts_on->gt(today()) => "scheduled",
            default => "active",
        };
    }

    public function valueLabel(): string
    {
        $value = qty((float) $this->value);

        return $this->discount_type === "percent" ? "−{$value}%" : "−" . money((float) $this->value);
    }

    /** Shape used by the order form to price lines in the browser. */
    public function toPricingArray(): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "type" => $this->discount_type,
            "value" => (float) $this->value,
            "all" => $this->applies_to === "all",
            "services" => $this->items->pluck("service_id")->filter()->map(fn ($id) => (int) $id)->values()->all(),
            "packages" => $this->items->pluck("package_id")->filter()->map(fn ($id) => (int) $id)->values()->all(),
            "starts" => $this->starts_on->toDateString(),
            "ends" => $this->ends_on?->toDateString(),
            "weekdays" => $this->weekdays ? array_map("intval", $this->weekdays) : null,
        ];
    }
}
