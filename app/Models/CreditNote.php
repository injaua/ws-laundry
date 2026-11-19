<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Nota de crédito: corrects (part of) an order's invoice. Settled either by handing
 * money back (refund — a "devolução") or by lowering what the customer still owes.
 */
class CreditNote extends Model
{
    use BelongsToStore;
    use Loggable;

    public const REASONS = ["complaint", "damage", "lost_item", "price_error", "service_not_done", "cancellation", "other"];

    public const SETTLEMENTS = ["refund", "deduct"];

    protected $fillable = [
        "number", "order_id", "customer_id", "complaint_id", "issued_at", "reason", "description",
        "amount", "net_amount", "vat_amount", "vat_rate", "settlement", "refund_method",
        "reverses_revenue", "cash_session_id", "user_id", "voided_at", "voided_by", "void_reason",
    ];

    protected function casts(): array
    {
        return [
            "issued_at" => "date",
            "voided_at" => "datetime",
            "amount" => "decimal:2",
            "net_amount" => "decimal:2",
            "vat_amount" => "decimal:2",
            "vat_rate" => "decimal:2",
            "reverses_revenue" => "boolean",
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, "voided_by");
    }

    /** The latest refund sent to the customer's mobile wallet (B2C) for this note. */
    public function mobileRefund(): HasOne
    {
        return $this->hasOne(MobileTransaction::class)->where("type", "b2c")->latestOfMany("id");
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull("voided_at");
    }

    public function scopeRefunds(Builder $query): Builder
    {
        return $query->where("settlement", "refund");
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isRefund(): bool
    {
        return $this->settlement === "refund";
    }

    /** NC2026/0001: sequential per year, never reused (voided notes keep their number). */
    public static function nextNumber(?\DateTimeInterface $date = null): string
    {
        $prefix = "NC" . ($date ? $date->format("Y") : now()->format("Y")) . "/";
        $next = NumberSequence::next($prefix, fn () => NumberSequence::highestSuffix(
            static::where("number", "like", $prefix . "%")->pluck("number"),
            $prefix,
        ));

        return NumberSequence::format($prefix, $next, 4);
    }

    /**
     * Reverse the revenue the order already booked, mirroring how it was booked:
     *  - cash sales book revenue on each payment, credit sales on delivery;
     *  - refund: D Prestação de serviços (7.2) + IVA liquidado (4.4.3.3) / C Caixa (1.1)
     *    or Depósitos (1.2.1); for a credit sale not yet delivered the revenue does not
     *    exist yet, so the refund only gives back the advance: D Clientes (4.1.1) / C Caixa;
     *  - deduct: only a delivered credit sale has revenue for the unpaid part:
     *    D 7.2 + 4.4.3.3 / C Clientes (4.1.1). Otherwise nothing was booked and the lower
     *    balance simply means less revenue when the rest is paid.
     */
    public function postToJournal(): ?JournalEntry
    {
        $order = $this->order;
        $revenueBooked = $this->reverses_revenue;
        $date = $this->issued_at->toDateString();
        $description = __("app.credit_note") . " " . $this->number . " · " . $order->order_number;

        if ($this->isRefund()) {
            $credit = Payment::accountFor($this->refund_method)?->id;
        } elseif ($order->is_credit && $revenueBooked) {
            $credit = Account::findByCode("4.1.1")?->id;
        } else {
            return null; // nothing to reverse
        }

        if (!$revenueBooked) {
            return JournalEntry::postPair(Account::findByCode("4.1.1")?->id, $credit, Money::round($this->amount), $date, $description, "credit_note", $this->id);
        }

        $services = Account::findByCode("7.2")?->id;
        $vatAccount = Money::isPositive($this->vat_amount) ? Account::findByCode("4.4.3.3")?->id : null;

        if ($vatAccount) {
            return JournalEntry::postLines([
                ["account_id" => $services, "debit" => Money::round($this->net_amount), "credit" => 0],
                ["account_id" => $vatAccount, "debit" => Money::round($this->vat_amount), "credit" => 0],
                ["account_id" => $credit, "debit" => 0, "credit" => Money::round($this->amount)],
            ], $date, $description, "credit_note", $this->id);
        }

        return JournalEntry::postPair($services, $credit, Money::round($this->amount), $date, $description, "credit_note", $this->id);
    }

    /** Whether posting is expected (used to warn when the chart of accounts is incomplete). */
    public function needsJournal(): bool
    {
        return $this->isRefund() || ($this->order->is_credit && $this->reverses_revenue);
    }
}
