<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One trip of a courier: picking clothes up at the customer's address, or
 * delivering a finished order there.
 *
 * requested (website, awaiting confirmation) → scheduled → on_the_way → done
 *                                                        ↘ failed → (rescheduled) scheduled
 * Any open job can be cancelled.
 */
class CourierJob extends Model
{
    use BelongsToStore;
    use Loggable;

    public const TYPES = ["pickup", "delivery"];

    public const STATUSES = ["requested", "scheduled", "on_the_way", "done", "failed", "cancelled"];

    public const OPEN_STATUSES = ["requested", "scheduled", "on_the_way", "failed"];

    public const WINDOWS = ["morning", "afternoon", "evening", "any"];

    protected $fillable = [
        "type", "status", "source", "customer_id", "order_id", "delivery_zone_id",
        "contact_name", "contact_phone", "address", "address_reference",
        "scheduled_date", "time_window", "courier_id", "fee", "notes", "outcome_notes",
        "failure_reason", "started_at", "completed_at", "cancelled_at", "created_by",
    ];

    protected function casts(): array
    {
        return [
            "scheduled_date" => "date",
            "fee" => "decimal:2",
            "started_at" => "datetime",
            "completed_at" => "datetime",
            "cancelled_at" => "datetime",
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, "delivery_zone_id");
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, "courier_id");
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn("status", self::OPEN_STATUSES);
    }

    /** Charged jobs: the fee of a cancelled trip is not billed. */
    public function scopeBillable(Builder $query): Builder
    {
        return $query->where("status", "!=", "cancelled");
    }

    public function isPickup(): bool
    {
        return $this->type === "pickup";
    }

    public function isDelivery(): bool
    {
        return $this->type === "delivery";
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** The courier can work on it: confirmed and not finished. */
    public function isActionable(): bool
    {
        return in_array($this->status, ["scheduled", "on_the_way"], true);
    }

    /** A picked-up bag that has not been turned into an order yet. */
    public function awaitsOrder(): bool
    {
        return $this->isPickup() && $this->status === "done" && $this->order_id === null;
    }

    public function statusColor(): string
    {
        return [
            "requested" => "warning",
            "scheduled" => "info",
            "on_the_way" => "primary",
            "done" => "success",
            "failed" => "danger",
            "cancelled" => "secondary",
        ][$this->status] ?? "secondary";
    }

    public function mapUrl(): string
    {
        return "https://www.google.com/maps/search/?api=1&query=" . rawurlencode(trim($this->address . " " . ($this->zone?->name ?? "")));
    }

    public function whatsappUrl(): ?string
    {
        $digits = preg_replace("/\D+/", "", $this->contact_phone);
        if ($digits === "") {
            return null;
        }

        return "https://wa.me/" . (strlen($digits) === 9 ? SystemSettings::phoneCountryDigits() . $digits : $digits);
    }

    /** What a delivery courier must collect: the order's open balance. */
    public function amountToCollect(): float
    {
        return $this->isDelivery() && $this->order ? max(0.0, $this->order->remainingBalance()) : 0.0;
    }
}
