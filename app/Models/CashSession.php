<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A till shift. The operator opens it with a cash float, every payment they take
 * is linked to it, cash can be moved to the safe (withdrawal) or added (deposit),
 * and at the end each payment method is counted blind against what the system expects.
 */
class CashSession extends Model
{
    use BelongsToStore;
    use Loggable;

    public const STATUS_OPEN = "open";

    public const STATUS_CLOSED = "closed";

    protected $fillable = ["user_id", "status", "opened_at", "opening_float", "closed_at", "closed_by", "closing_note"];

    protected function casts(): array
    {
        return [
            "opened_at" => "datetime",
            "closed_at" => "datetime",
            "opening_float" => "decimal:2",
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, "closed_by");
    }

    /**
     * What the cashier checks at closing for non-cash methods, per method: one line per
     * reference ("ref · order · phone"); a cheque covering several orders is listed once.
     *
     * @return array<string, Collection<int, string>>
     */
    public function paymentReferences(): array
    {
        return $this->payments()->where("method", "!=", "cash")->with(["order:id,order_number", "cheque"])->orderBy("id")->get()
            ->groupBy("method")
            ->map(fn ($payments, $method) => $method === "cheque"
                ? $payments->groupBy("cheque_id")->map(fn ($g) => ($g->first()->cheque?->label() ?? $g->first()->transaction_ref) . " · " . $g->pluck("order.order_number")->filter()->implode(", "))->values()
                : $payments->map(fn ($p) => ($p->transaction_ref ?? "—") . " · " . $p->order?->order_number . ($p->payer_phone ? " · " . $p->payer_phone : ""))->values())
            ->all();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function counts(): HasMany
    {
        return $this->hasMany(CashSessionCount::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where("status", self::STATUS_OPEN);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Credit notes refunded from this till (voided ones excluded). */
    public function refunds(): HasMany
    {
        return $this->hasMany(CreditNote::class)->whereNull("voided_at")->where("settlement", "refund");
    }

    public static function currentFor(User $user): ?self
    {
        return static::open()->where("user_id", $user->id)->latest("opened_at")->first();
    }

    /**
     * What the system expects for each payment method right now: payments minus the
     * refunds handed back from this till. Cash also carries the opening float and the
     * withdrawals/deposits made during the shift.
     *
     * @return array<string, float>
     */
    public function expectedByMethod(): array
    {
        $byMethod = $this->payments()
            ->selectRaw("method, SUM(amount) as total")
            ->groupBy("method")
            ->pluck("total", "method");

        $refunds = $this->refunds()
            ->selectRaw("refund_method, SUM(amount) as total")
            ->groupBy("refund_method")
            ->pluck("total", "refund_method");

        $expected = [];
        foreach (Payment::METHODS as $method) {
            $expected[$method] = Money::sub($byMethod[$method] ?? 0, $refunds[$method] ?? 0);
        }

        $expected["cash"] = Money::add($expected["cash"], $this->opening_float, $this->netMovements());

        return $expected;
    }

    public function expectedCash(): float
    {
        return $this->expectedByMethod()["cash"];
    }

    /** Deposits minus withdrawals. */
    public function netMovements(): float
    {
        $deposits = Money::sum($this->movements()->where("type", CashMovement::TYPE_DEPOSIT)->pluck("amount"));
        $withdrawals = Money::sum($this->movements()->where("type", CashMovement::TYPE_WITHDRAWAL)->pluck("amount"));

        return Money::sub($deposits, $withdrawals);
    }

    /** Closing counts in the same order as Payment::METHODS, as everywhere else in the app. */
    public function orderedCounts()
    {
        return $this->counts->sortBy(fn ($c) => array_search($c->method, Payment::METHODS, true))->values();
    }

    public function totalDifference(): float
    {
        return Money::sum($this->counts->pluck("difference"));
    }

    public function hasDifferences(): bool
    {
        return $this->counts->contains(fn ($c) => !Money::isZero($c->difference));
    }

    public function label(): string
    {
        return "CX-" . str_pad((string) $this->id, 5, "0", STR_PAD_LEFT);
    }
}
