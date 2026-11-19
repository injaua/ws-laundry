<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A request sent to a mobile money operator: C2B (the customer confirms with the PIN
 * on the phone and the laundry receives) or B2C (refund to the customer's wallet).
 */
class MobileTransaction extends Model
{
    use BelongsToStore;

    public const TYPES = ["c2b", "b2c"];

    /** Operators with an API integration (mKesh is recorded manually). */
    public const PROVIDERS = ["mpesa", "emola"];

    public const STATUSES = ["pending", "processing", "success", "failed", "expired"];

    public const OPEN_STATUSES = ["pending", "processing"];

    public const CHANNELS = ["counter", "courier", "link"];

    /** A request without an answer for this long is considered expired. */
    public const EXPIRE_MINUTES = 10;

    protected $fillable = [
        "type", "provider", "reference", "order_id", "customer_id", "credit_note_id", "courier_job_id", "payment_id",
        "user_id", "channel", "phone", "amount", "status", "provider_ref", "conversation_id", "response_code",
        "response_message", "raw", "attempts", "completed_at",
    ];

    protected function casts(): array
    {
        return [
            "amount" => "decimal:2",
            "raw" => "array",
            "completed_at" => "datetime",
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function courierJob(): BelongsTo
    {
        return $this->belongsTo(CourierJob::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn("status", self::OPEN_STATUSES);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isSuccessful(): bool
    {
        return $this->status === "success";
    }

    public function statusColor(): string
    {
        return ["pending" => "secondary", "processing" => "primary", "success" => "success", "failed" => "danger", "expired" => "warning"][$this->status] ?? "secondary";
    }

    /** Short alphanumeric reference accepted by both operators. */
    public static function newReference(string $type): string
    {
        do {
            $ref = strtoupper(($type === "b2c" ? "R" : "P") . now()->format("ymd") . Str::random(7));
            $ref = preg_replace("/[^A-Z0-9]/", "X", $ref);
        } while (static::where("reference", $ref)->exists());

        return $ref;
    }

    /** Mozambican number in international form without "+": 2588XXXXXXXX. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace("/\D/", "", (string) $phone);
        if (strlen($digits) === 9 && str_starts_with($digits, "8")) {
            $digits = "258" . $digits;
        }

        return preg_match("/^2588[2-7]\d{7}$/", $digits) ? $digits : null;
    }

    /** Which wallet a number belongs to (Vodacom 84/85 = M-Pesa, Movitel 86/87 = e-Mola). */
    public static function providerForPhone(?string $phone): ?string
    {
        $normalized = self::normalizePhone($phone);
        if (!$normalized) {
            return null;
        }

        return match (substr($normalized, 3, 2)) {
            "84", "85" => "mpesa",
            "86", "87" => "emola",
            default => null,
        };
    }
}
