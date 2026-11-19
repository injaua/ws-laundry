<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A cheque received from a customer. One cheque can settle several orders (grouped
 * payment), so its payments share it. Life cycle:
 * in_hand (till / safe) -> deposited (bank deposit) -> cleared, or bounced at any
 * point before clearing (the payments are then cancelled and the debt comes back).
 */
class Cheque extends Model
{
    use BelongsToStore;
    use Loggable;

    public const STATUSES = ["in_hand", "deposited", "cleared", "bounced"];

    /** Banks operating in Mozambique, offered as suggestions (free text is accepted). */
    public const BANKS = ["BCI", "Millennium bim", "Standard Bank", "Absa", "Moza Banco", "FNB", "Nedbank", "Access Bank", "Ecobank", "First Capital Bank", "Letshego", "Société Générale", "BNI", "Banco Mais", "UBA", "MyBucks"];

    /** Who may pay by cheque (business setting "cheque_policy"). */
    public const POLICIES = ["off", "business", "all"];

    protected $fillable = ["customer_id", "number", "bank", "drawer", "cheque_date", "amount", "status", "bank_deposit_id", "deposited_at", "cleared_at", "bounced_at", "bounce_reason", "user_id"];

    protected function casts(): array
    {
        return [
            "cheque_date" => "date",
            "amount" => "decimal:2",
            "deposited_at" => "datetime",
            "cleared_at" => "datetime",
            "bounced_at" => "datetime",
        ];
    }

    public static function policy(): string
    {
        $policy = (string) SiteSetting::get("cheque_policy", "business");

        return in_array($policy, self::POLICIES, true) ? $policy : "business";
    }

    public static function normalizeNumber(?string $number): string
    {
        return strtoupper(preg_replace("/[^A-Za-z0-9]/", "", (string) $number));
    }

    public static function normalizeBank(?string $bank): string
    {
        $bank = trim(preg_replace("/\s+/", " ", (string) $bank));
        foreach (self::BANKS as $known) {
            if (mb_strtolower($known) === mb_strtolower($bank)) {
                return $known;
            }
        }

        return $bank;
    }

    /** The same cheque (bank + number) cannot be recorded twice. */
    public static function exists(string $bank, string $number, ?int $ignoreId = null): bool
    {
        return static::whereRaw("lower(bank) = ?", [mb_strtolower(self::normalizeBank($bank))])
            ->where("number", self::normalizeNumber($number))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Payments including the ones cancelled by a bounce. */
    public function allPayments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScope("not_bounced");
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(BankDeposit::class, "bank_deposit_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeInHand(Builder $query): Builder
    {
        return $query->where("status", "in_hand");
    }

    public function isInHand(): bool
    {
        return $this->status === "in_hand";
    }

    public function isPostDated(): bool
    {
        return $this->cheque_date->isFuture();
    }

    /** Can go to the bank on the given day (post-dated cheques must wait for their date). */
    public function canBeDepositedOn(\DateTimeInterface|string $date): bool
    {
        return $this->isInHand() && $this->cheque_date->lte(Carbon::parse($date)->endOfDay());
    }

    public function canBounce(): bool
    {
        return in_array($this->status, ["in_hand", "deposited"], true);
    }

    public function statusColor(): string
    {
        return self::colorFor($this->status);
    }

    /** Bootstrap colour of a cheque status. */
    public static function colorFor(?string $status): string
    {
        return match ($status) {
            "in_hand" => "primary",
            "deposited" => "info",
            "cleared" => "success",
            "bounced" => "danger",
            default => "secondary",
        };
    }

    /** Amount = what its (valid) payments add up to. */
    public function refreshAmount(): void
    {
        $this->update(["amount" => round((float) $this->allPayments()->sum("amount"), 2)]);
    }

    public function label(): string
    {
        return "{$this->bank} nº {$this->number}";
    }
}
