<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class Order extends Model
{
    use BelongsToStore;
    use HasFactory, Loggable, SoftDeletes;

    public const STATUSES = [
        'received', 'washing', 'drying', 'ironing', 'folding', 'ready', 'delivering', 'completed',
    ];

    protected $fillable = [
        'order_number', 'customer_id', 'received_at', 'delivery_date', 'ready_at', 'completed_at',
        'materials_consumed_at', 'consumed_stages', 'status', 'total_price', 'subtotal', 'is_express', 'express_fee', 'delivery_fee', 'vat_rate', 'vat_amount', 'notes',
        'is_credit', 'due_date', 'credit_override_note', 'purchase_order_ref', 'invoice_id',
        'abandoned_at', 'abandon_destination', 'abandon_note', 'abandoned_by',
        'care_flags', 'intake_notes', 'original_delivery_date', 'delay_reason', 'qc_passed_at', 'qc_by',
        'rework_count', 'rework_of_order_id',
    ];

    /** What happens to garments never collected. */
    public const ABANDON_DESTINATIONS = ['donated', 'discarded', 'sold', 'kept', 'other'];

    protected function casts(): array
    {
        return [
            'received_at' => 'date',
            'delivery_date' => 'date',
            'ready_at' => 'datetime',
            'completed_at' => 'date',
            'materials_consumed_at' => 'datetime',
            'consumed_stages' => 'array',
            'subtotal' => 'decimal:2',
            'total_price' => 'decimal:2',
            'is_express' => 'boolean',
            'express_fee' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'is_credit' => 'boolean',
            'due_date' => 'date',
            'abandoned_at' => 'datetime',
            'care_flags' => 'array',
            'original_delivery_date' => 'date',
            'qc_passed_at' => 'datetime',
        ];
    }

    /** Garment-care instructions taken at intake; shown on the board, the order and the label. */
    public const CARE_FLAGS = ["delicate", "separate_colors", "no_softener", "low_temperature", "hand_wash", "dry_clean", "no_tumble_dry", "stain_treatment"];

    /** Production stages shown on the board (before the order is ready). */
    public const PRODUCTION_STAGES = ["received", "washing", "drying", "ironing", "folding"];

    /** Who is doing the stage change (set by the production board), for the stage history. */
    public static ?int $stageEmployeeId = null;

    public static ?string $stageNote = null;

    protected static function booted(): void
    {
        // Remember when the order first became ready (for the "not collected" reminders).
        static::saving(function (Order $order) {
            if ($order->isDirty("status") && $order->status === "ready" && $order->ready_at === null) {
                $order->ready_at = now();
            }
        });

        // Stage history: every status change, whatever path made it (board, scan, payment, courier).
        static::created(fn (Order $order) => $order->logStage(null, $order->status));
        static::updated(function (Order $order) {
            if ($order->wasChanged("status")) {
                $order->logStage($order->getOriginal("status"), $order->status);
            }
        });
    }

    public function logStage(?string $from, string $to): void
    {
        OrderStageLog::create([
            "order_id" => $this->id,
            "from_status" => $from,
            "to_status" => $to,
            "user_id" => auth()->id(),
            "employee_id" => static::$stageEmployeeId,
            "note" => static::$stageNote,
            "created_at" => now(),
        ]);
    }

    /** Public payment page of this order (signed, valid 30 days). */
    public function payLink(): string
    {
        return URL::temporarySignedRoute("pay.show", now()->addDays(30), ["order" => $this->id]);
    }

    public function mobileTransactions()
    {
        return $this->hasMany(MobileTransaction::class)->latest("id");
    }

    public function stageLogs()
    {
        return $this->hasMany(OrderStageLog::class)->orderBy("created_at")->orderBy("id");
    }

    public function photos()
    {
        return $this->hasMany(OrderPhoto::class);
    }

    public function reworks()
    {
        return $this->hasMany(OrderRework::class)->latest("created_at");
    }

    public function machineLoads()
    {
        return $this->belongsToMany(MachineLoad::class, "machine_load_order")->withPivot("weight_kg");
    }

    public function reworkOf()
    {
        return $this->belongsTo(Order::class, "rework_of_order_id");
    }

    public function qcUser()
    {
        return $this->belongsTo(User::class, "qc_by");
    }

    /** Still in production (not ready yet) and not abandoned. */
    public function scopeInProduction($query)
    {
        return $query->whereIn("status", self::PRODUCTION_STAGES)->whereNull("abandoned_at");
    }

    /** Promised date already passed and the order is not ready. */
    public function scopeLate($query)
    {
        return $query->inProduction()->whereNotNull("delivery_date")->whereDate("delivery_date", "<", today());
    }

    /** Promised for today and not ready yet. */
    public function scopeDueToday($query)
    {
        return $query->inProduction()->whereDate("delivery_date", today());
    }

    public function isLate(): bool
    {
        return in_array($this->status, self::PRODUCTION_STAGES, true) && $this->delivery_date && $this->delivery_date->lt(today());
    }

    public function isDueToday(): bool
    {
        return in_array($this->status, self::PRODUCTION_STAGES, true) && $this->delivery_date && $this->delivery_date->isToday();
    }

    /** Kilos to wash: weight lines plus package weights. */
    public function weightKg(): float
    {
        $items = $this->relationLoaded("items") ? $this->items : $this->items()->get();

        return round((float) $items->whereIn("type", ["by_weight", "by_package"])->sum("weight"), 2);
    }

    /** Garments counted by the piece (ironing workload). */
    public function pieceCount(): int
    {
        $items = $this->relationLoaded("items") ? $this->items : $this->items()->get();

        return (int) $items->where("type", "by_piece")->sum("amount");
    }

    /** When the order entered its current stage. */
    public function stageSince(): ?Carbon
    {
        $log = $this->relationLoaded("stageLogs")
            ? $this->stageLogs->where("to_status", $this->status)->last()
            : $this->stageLogs()->where("to_status", $this->status)->latest("id")->first();

        return $log?->created_at;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Payments undone because their cheque bounced (hidden from payments()). */
    public function bouncedPayments()
    {
        return $this->hasMany(Payment::class)->withoutGlobalScope("not_bounced")->whereNotNull("bounced_at");
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class)->orderBy("id");
    }

    public function abandoner()
    {
        return $this->belongsTo(User::class, "abandoned_by");
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class)->latest();
    }

    /** Ready and waiting for the customer, not yet given up on. */
    public function scopeUnclaimed($query)
    {
        return $query->whereIn("status", ["ready", "delivering"])->whereNotNull("ready_at")->whereNull("abandoned_at");
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function courierJobs()
    {
        return $this->hasMany(CourierJob::class);
    }

    /**
     * Rebuilds subtotal, VAT and total from the lines plus the express and delivery
     * fees. The delivery fee is the sum of the order's non-cancelled courier trips.
     */
    public function recalculateTotals(): void
    {
        $itemsSubtotal = (float) $this->items()->sum("subtotal");
        $deliveryFee = (float) $this->courierJobs()->billable()->sum("fee");
        $subtotal = Money::round($itemsSubtotal + (float) $this->express_fee + $deliveryFee);
        $vatAmount = Money::percent($subtotal, $this->vat_rate);

        $this->update([
            "delivery_fee" => Money::round($deliveryFee),
            "subtotal" => $subtotal,
            "vat_amount" => $vatAmount,
            "total_price" => Money::round($subtotal + $vatAmount),
        ]);
    }

    /** Value of the clothes/packages only (before fees and VAT): used for free-delivery thresholds. */
    public function itemsSubtotal(): float
    {
        return (float) $this->items()->sum("subtotal");
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /** On a consolidated invoice that was issued: billing data is frozen. */
    public function isOnIssuedInvoice(): bool
    {
        return $this->invoice_id !== null && $this->invoice?->isIssued();
    }

    public function getStatusLabelAttribute()
    {
        return __('app.status_' . $this->status);
    }

    public function getStatusColorAttribute()
    {
        $colors = [
            'received' => 'secondary',
            'washing' => 'info',
            'drying' => 'primary',
            'ironing' => 'warning',
            'folding' => 'info',
            'ready' => 'success',
            'delivering' => 'primary',
            'completed' => 'success',
        ];

        return $colors[$this->status] ?? 'secondary';
    }

    /**
     * Next order number for the given day (ORDyymmddNNN). Based on the highest
     * existing sequence for that prefix — including soft-deleted orders — rather
     * than a row count, so gaps or deletions never produce a duplicate. Callers
     * must still retry on a unique-constraint violation to cover concurrent inserts.
     */
    public static function nextOrderNumber(?\DateTimeInterface $date = null): string
    {
        $prefix = "ORD" . ($date ? $date->format("ymd") : now()->format("ymd"));

        $last = static::withTrashed()
            ->where("order_number", "like", $prefix . "%")
            ->pluck("order_number")
            ->map(fn ($number) => substr($number, strlen($prefix)))
            ->filter(fn ($suffix) => ctype_digit($suffix))
            ->map(fn ($suffix) => (int) $suffix)
            ->max() ?? 0;

        return $prefix . str_pad((string) ($last + 1), 3, "0", STR_PAD_LEFT);
    }

    public function pieces()
    {
        return $this->hasMany(OrderPiece::class)->orderBy("seq");
    }

    public function statusRank(?string $status = null): int
    {
        $rank = array_search($status ?? $this->status, self::STATUSES, true);

        return $rank === false ? 0 : $rank;
    }

    public function allowedStatuses(): array
    {
        return array_slice(self::STATUSES, $this->statusRank());
    }

    public function canTransitionTo(string $status): bool
    {
        return array_search($status, self::STATUSES, true) !== false
            && $this->statusRank($status) >= $this->statusRank();
    }

    /**
     * Split a VAT-inclusive amount into [net, vat] using this order's snapshot rate.
     */
    public function splitGrossAmount(float $amount): array
    {
        return Money::splitGross($amount, $this->vat_rate);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum("amount");
    }

    /** Valid credit notes (refunded or deducted). */
    public function creditedAmount(): float
    {
        return (float) $this->creditNotes()->valid()->sum("amount");
    }

    /** Money handed back to the customer through credit notes. */
    public function refundedAmount(): float
    {
        return (float) $this->creditNotes()->valid()->refunds()->sum("amount");
    }

    /** Invoice total after credit notes. */
    public function netTotal(): float
    {
        return round((float) $this->total_price - $this->creditedAmount(), 2);
    }

    /** What was received and kept: payments minus refunds. */
    public function netPaid(): float
    {
        return round($this->paidAmount() - $this->refundedAmount(), 2);
    }

    /** Still owed: total − credit notes − (payments − refunds). */
    /**
     * Money on the order page: paid, credited (valid credit notes), refunded and what is still due.
     * Uses the loaded payments / creditNotes relations.
     *
     * @return array{paid: float, credited: float, refunded: float, remaining: float}
     */
    public function paymentSummary(): array
    {
        $paid = (float) $this->payments->sum("amount");
        $validNotes = $this->creditNotes->whereNull("voided_at");
        $credited = (float) $validNotes->sum("amount");
        $refunded = (float) $validNotes->where("settlement", "refund")->sum("amount");

        return [
            "paid" => $paid,
            "credited" => $credited,
            "refunded" => $refunded,
            "remaining" => round($this->total_price - $credited - ($paid - $refunded), 2),
        ];
    }

    public function remainingBalance(): float
    {
        return round($this->netTotal() - $this->netPaid(), 2);
    }

    public function isOverdue(): bool
    {
        return $this->is_credit
            && $this->status === "completed"
            && $this->remainingBalance() > 0
            && $this->due_date
            && $this->due_date->isPast();
    }

    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()->startOfDay(), false) : 0;
    }

    /**
     * Credit orders whose balance is still open: issued (completed), unpaid, not deleted.
     */
    public function scopeOutstandingCredit($query)
    {
        return $query->where("is_credit", true)
            ->whereNull("deleted_at")
            ->where("status", "completed")
            ->whereRaw("total_price - coalesce((select sum(amount) from payments where payments.order_id = orders.id and payments.bounced_at is null), 0)"
                . " - coalesce((select sum(amount) from credit_notes where credit_notes.order_id = orders.id and credit_notes.voided_at is null and credit_notes.settlement = 'deduct'), 0) > 0");
    }

    public function scopeOverdueCredit($query)
    {
        return $query->outstandingCredit()->whereDate("due_date", "<", now()->toDateString());
    }

    /**
     * Bill a completed credit sale to accounts receivable:
     * Debit Clientes c/c (4.1.1) with the gross total; credit Prestação de serviços (7.2)
     * with the net part and, when the order carries VAT, IVA liquidado (4.4.3.3).
     */
    public function postCreditSaleToJournal(): ?JournalEntry
    {
        $receivable = Account::findByCode("4.1.1")?->id;
        $services = Account::findByCode("7.2")?->id;
        // Credit notes issued before delivery already lowered the invoice.
        $amount = $this->netTotal();

        if (!$receivable || !$services || $amount <= 0) {
            return null;
        }

        $date = ($this->completed_at ?? now())->toDateString();
        $description = __("app.credit_sale") . " " . $this->order_number;
        [$net, $vat] = $this->splitGrossAmount($amount);

        if ($vat > 0 && $vatAccount = Account::findByCode("4.4.3.3")?->id) {
            return JournalEntry::postLines([
                ["account_id" => $receivable, "debit" => $amount, "credit" => 0],
                ["account_id" => $services, "debit" => 0, "credit" => $net],
                ["account_id" => $vatAccount, "debit" => 0, "credit" => $vat],
            ], $date, $description, "credit_sale", $this->id);
        }

        return JournalEntry::postPair($receivable, $services, $amount, $date, $description, "credit_sale", $this->id);
    }
}
