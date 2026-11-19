<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One WhatsApp message, inbound or outbound. A conversation is every message
 * exchanged with one phone number.
 */
class WhatsAppMessage extends Model
{
    use BelongsToStore;

    protected $table = "whatsapp_messages";

    public const IN = "in";

    public const OUT = "out";

    /** Ranked so a late "sent" callback never overwrites "read". */
    public const STATUS_RANK = ["queued" => 0, "sent" => 1, "delivered" => 2, "read" => 3, "failed" => 4];

    protected $fillable = [
        "phone", "customer_id", "direction", "type", "body", "template_name", "meta", "status",
        "provider_message_id", "error", "user_id", "order_id", "template_id",
        "notification_key", "read_by_staff_at", "status_at", "channel",
    ];

    protected function casts(): array
    {
        return [
            "meta" => "array",
            "read_by_staff_at" => "datetime",
            "status_at" => "datetime",
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function scopeInbound(Builder $q): Builder
    {
        return $q->where("direction", self::IN);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->inbound()->whereNull("read_by_staff_at");
    }

    public function isInbound(): bool
    {
        return $this->direction === self::IN;
    }

    public function isAutomatic(): bool
    {
        return $this->notification_key !== null;
    }

    /** Typed directly on the phone linked by QR code, not in the platform. */
    public function isFromPhone(): bool
    {
        return ($this->meta["source"] ?? null) === "phone";
    }

    /** Apply a delivery status from the provider, never moving backwards (except to failed). */
    public function applyStatus(string $status, ?string $error = null): void
    {
        $current = self::STATUS_RANK[$this->status] ?? -1;
        $next = self::STATUS_RANK[$status] ?? null;

        if ($next === null || ($next <= $current && $status !== "failed")) {
            return;
        }

        $this->update(["status" => $status, "error" => $error, "status_at" => now()]);
    }

    /** True while Meta still allows free-form text: within 24h of the customer's last message. */
    public static function sessionOpen(string $phone, ?string $channel = null): bool
    {
        // The window belongs to one number: a message to the QR-linked number does not
        // open it on the official one. Rows from before channels existed count everywhere.
        $last = static::inbound()->where("phone", $phone)
            ->when($channel, fn ($q) => $q->where(fn ($q) => $q->where("channel", $channel)->orWhereNull("channel")))
            ->max("created_at");

        return $last !== null
            && now()->diffInHours($last, true) < (int) config("whatsapp.session_window_hours", 24);
    }
}
