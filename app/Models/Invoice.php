<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use App\Support\NumberSequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consolidated invoice grouping several completed credit orders of one customer.
 *
 * Revenue is still booked per order on completion (D 4.1.1 / C 7.2 + IVA); the invoice
 * is the billing document: it fixes one due date for all its orders and is what the
 * customer pays against. Totals are a snapshot taken when the invoice is issued; the
 * outstanding balance is always computed live from the orders.
 */
class Invoice extends Model
{
    use BelongsToStore;
    use Loggable;

    public const STATUSES = ["draft", "issued", "void"];

    protected $fillable = [
        "number", "customer_id", "period_start", "period_end", "issue_date", "due_date",
        "subtotal", "vat_amount", "total", "status", "notes", "created_by",
        "issued_at", "voided_at", "void_reason",
    ];

    protected function casts(): array
    {
        return [
            "period_start" => "date",
            "period_end" => "date",
            "issue_date" => "date",
            "due_date" => "date",
            "subtotal" => "decimal:2",
            "vat_amount" => "decimal:2",
            "total" => "decimal:2",
            "issued_at" => "datetime",
            "voided_at" => "datetime",
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->orderBy("completed_at")->orderBy("id");
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy("paid_at")->orderBy("id");
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function scopeIssued($query)
    {
        return $query->where("status", "issued");
    }

    public function isDraft(): bool
    {
        return $this->status === "draft";
    }

    public function isIssued(): bool
    {
        return $this->status === "issued";
    }

    public function isVoid(): bool
    {
        return $this->status === "void";
    }

    /** Still owed on the invoice's orders (after payments and credit notes). */
    public function balance(): float
    {
        return Money::sum($this->orders->map(fn (Order $order) => Money::max(0, $order->remainingBalance())));
    }

    /** Paid against the invoice's orders (net of refunds). */
    public function paidAmount(): float
    {
        return Money::sum($this->orders->map(fn (Order $order) => $order->netPaid()));
    }

    public function isPaid(): bool
    {
        return $this->isIssued() && !Money::isPositive($this->balance());
    }

    public function isOverdue(): bool
    {
        return $this->isIssued() && $this->due_date && $this->due_date->isPast() && Money::isPositive($this->balance());
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()->startOfDay(), false) : 0;
    }

    /** Status shown to users: draft, void, paid, overdue or issued (open). */
    public function displayStatus(): string
    {
        return match (true) {
            $this->isDraft() => "draft",
            $this->isVoid() => "void",
            $this->isPaid() => "paid",
            $this->isOverdue() => "overdue",
            default => "issued",
        };
    }

    public function statusColor(): string
    {
        return [
            "draft" => "secondary",
            "void" => "dark",
            "paid" => "success",
            "overdue" => "danger",
            "issued" => "primary",
        ][$this->displayStatus()];
    }

    public function label(): string
    {
        return $this->number ?? __("app.invoice_draft_label", ["id" => $this->id]);
    }

    /** Totals from the orders as they stand now (credit notes lower them). */
    public function recalculate(): void
    {
        $this->load("orders");
        $totals = [];
        $vats = [];

        foreach ($this->orders as $order) {
            $gross = $order->netTotal();
            $totals[] = $gross;
            $vats[] = $order->splitGrossAmount($gross)[1];
        }

        $total = Money::sum($totals);
        $vat = Money::sum($vats);

        $this->update([
            "total" => $total,
            "vat_amount" => $vat,
            "subtotal" => Money::sub($total, $vat),
        ]);
    }

    /** Next number in the yearly series FT-YYYY/NNNN. */
    public static function nextNumber(int $year): string
    {
        $prefix = "FT-{$year}/";
        $next = NumberSequence::next($prefix, fn () => NumberSequence::highestSuffix(
            static::where("number", "like", $prefix . "%")->pluck("number"),
            $prefix,
        ));

        return NumberSequence::format($prefix, $next, 4);
    }
}
