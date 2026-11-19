<?php

namespace App\Models;

use App\Models\Traits\BelongsToStore;
use App\Models\Traits\Loggable;
use App\Services\Payments\Cheques;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use BelongsToStore;
    use Loggable;

    public const METHODS = ["cash", "transfer", "cheque", "mpesa", "emola", "mkesh"];

    /** Mobile wallets: the operator's transaction reference is mandatory and unique. */
    public const MOBILE_METHODS = ["mpesa", "emola", "mkesh"];

    /** Methods whose reference (operator ID / transfer proof number) is mandatory and unique. */
    public const UNIQUE_REF_METHODS = ["transfer", "mpesa", "emola", "mkesh"];

    /** Ledger account per method (PGC-NIRF class 1). Cheques wait in 1.3 until deposited. */
    public const ACCOUNTS = ["cash" => "1.1", "transfer" => "1.2.1", "cheque" => "1.3", "mpesa" => "1.2.2", "emola" => "1.2.3", "mkesh" => "1.2.4"];

    protected $fillable = ["order_id", "user_id", "cash_session_id", "paid_at", "amount", "tendered", "method", "transaction_ref", "cheque_id", "payer_phone", "reconciled_at", "bounced_at", "note", "group_ref", "invoice_id", "courier_job_id", "handed_over_at", "handed_over_by"];

    protected function casts(): array
    {
        return [
            "amount" => "decimal:2",
            "tendered" => "decimal:2",
            "paid_at" => "date",
            "handed_over_at" => "datetime",
            "reconciled_at" => "datetime",
            "bounced_at" => "datetime",
        ];
    }

    /**
     * A bounced cheque never paid anything: its payments disappear from every balance,
     * report and till total. Use withoutGlobalScope("not_bounced") to see them.
     */
    protected static function booted(): void
    {
        static::addGlobalScope("not_bounced", fn ($query) => $query->whereNull("payments.bounced_at"));
    }

    /** Methods a refund can be paid with (the business does not issue cheques here). */
    public static function refundMethods(): array
    {
        return array_values(array_diff(self::METHODS, ["cheque"]));
    }

    /** Methods a courier can collect on the road (cheques are taken at the counter). */
    public static function courierMethods(): array
    {
        return array_values(array_diff(self::METHODS, ["cheque"]));
    }

    /** Methods offered in a payment form: cheques only when the policy allows them. */
    public static function formMethods(?Customer $customer = null): array
    {
        return array_values(array_filter(self::METHODS, fn ($m) => $m !== "cheque" || Cheques::acceptedFrom($customer)));
    }

    public function isCheque(): bool
    {
        return $this->method === "cheque";
    }

    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheque::class);
    }

    /** Cash handed back to the customer. */
    public function change(): ?float
    {
        return $this->method === "cash" && $this->tendered !== null ? Money::max(0, Money::sub($this->tendered, $this->amount)) : null;
    }

    public static function isMobile(?string $method): bool
    {
        return in_array($method, self::MOBILE_METHODS, true);
    }

    /** Ledger account of a payment method; wallets fall back to the bank when their account is missing. */
    public static function accountFor(?string $method): ?Account
    {
        $code = self::ACCOUNTS[$method] ?? "1.2.1";

        return Account::findByCode($code) ?? Account::findByCode($method === "cash" ? "1.1" : "1.2.1");
    }

    public static function normalizeRef(?string $ref): ?string
    {
        $ref = strtoupper(preg_replace("/\s+/", "", (string) $ref));

        return $ref === "" ? null : $ref;
    }

    /**
     * Was this operator reference already used? Payments of one grouped receipt (one
     * transfer split over several orders) share their reference.
     */
    public static function referenceTaken(string $method, string $ref, ?string $groupRef = null, ?int $ignoreId = null): bool
    {
        return static::where("method", $method)->where("transaction_ref", self::normalizeRef($ref))
            ->when($groupRef, fn ($q) => $q->where(fn ($w) => $w->whereNull("group_ref")->orWhere("group_ref", "!=", $groupRef)))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Validation rules for the reference fields of a manual payment form.
     * Use together with Payment::validateReference() after the basic validation.
     */
    public static function referenceRules(): array
    {
        return [
            "transaction_ref" => ["nullable", "required_if:method,transfer,cheque,mpesa,emola,mkesh", "string", "max:40", "regex:/^[A-Za-z0-9.\-\/\s]+$/"],
            "payer_phone" => ["nullable", "string", "max:20", "regex:/^[0-9+\s]+$/"],
            "cheque_bank" => ["nullable", "required_if:method,cheque", "string", "max:60"],
            "cheque_date" => ["nullable", "date", "before_or_equal:+180 days"],
            "cheque_drawer" => ["nullable", "string", "max:120"],
            "tendered" => ["nullable", "numeric", "min:0", "max:100000000"],
        ];
    }

    /** Normalizes the reference and rejects one already used. Returns the cleaned data. */
    public static function validateReference(array $data, ?int $ignoreId = null): array
    {
        $method = $data["method"] ?? null;

        // Change is only for cash, and the customer must hand over at least the amount.
        if ($method !== "cash" || !isset($data["tendered"]) || $data["tendered"] === "") {
            $data["tendered"] = null;
        } elseif (Money::compare((float) $data["tendered"], (float) ($data["amount"] ?? 0)) < 0) {
            throw ValidationException::withMessages(["tendered" => __("app.tendered_too_low")]);
        }

        if ($method === "cheque") {
            $data["transaction_ref"] = Cheque::normalizeNumber($data["transaction_ref"] ?? null);
            $data["cheque_bank"] = Cheque::normalizeBank($data["cheque_bank"] ?? null);
            $data["payer_phone"] = null;
            $ignoreCheque = $ignoreId ? static::whereKey($ignoreId)->value("cheque_id") : null;
            if ($data["transaction_ref"] !== "" && Cheque::exists($data["cheque_bank"], $data["transaction_ref"], $ignoreCheque)) {
                throw ValidationException::withMessages([
                    "transaction_ref" => __("app.cheque_taken", ["cheque" => $data["cheque_bank"] . " nº " . $data["transaction_ref"]]),
                ]);
            }

            return $data;
        }

        if (!in_array($method, self::UNIQUE_REF_METHODS, true)) {
            $data["transaction_ref"] = self::normalizeRef($data["transaction_ref"] ?? null);
            $data["payer_phone"] = $data["payer_phone"] ?? null;

            return $data;
        }

        $data["transaction_ref"] = self::normalizeRef($data["transaction_ref"] ?? null);
        $data["payer_phone"] = isset($data["payer_phone"]) ? preg_replace("/\s+/", "", $data["payer_phone"]) : null;

        if ($data["transaction_ref"] && self::referenceTaken($data["method"], $data["transaction_ref"], null, $ignoreId)) {
            throw ValidationException::withMessages([
                "transaction_ref" => __("app.transaction_ref_taken", ["ref" => $data["transaction_ref"]]),
            ]);
        }

        return $data;
    }

    public function mobileTransaction(): HasOne
    {
        return $this->hasOne(MobileTransaction::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courierJob(): BelongsTo
    {
        return $this->belongsTo(CourierJob::class);
    }

    public function handedOverTo(): BelongsTo
    {
        return $this->belongsTo(User::class, "handed_over_by");
    }

    /** Cash a courier collected on delivery that has not reached a till yet. */
    public function scopePendingHandover(Builder $query): Builder
    {
        return $query->whereNotNull("courier_job_id")->where("method", "cash")->whereNull("cash_session_id")->whereNull("handed_over_at");
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** Payments counted in a closed till shift are frozen: the count already happened. */
    public function isInClosedCashSession(): bool
    {
        return $this->cash_session_id !== null && $this->cashSession?->isOpen() === false;
    }

    /**
     * Debit Caixa (1.1) or Depósitos à ordem (1.2.1). Immediate sales credit Prestação de
     * serviços (7.2) with the net part and, when the order carries VAT, IVA liquidado
     * (4.4.3.3) with the tax part. Credit orders already booked their revenue on
     * completion, so the payment only settles the receivable: credit Clientes c/c (4.1.1).
     * Returns null when the chart of accounts is missing a needed account.
     */
    public function postToJournal(): ?JournalEntry
    {
        $this->loadMissing("order");
        $order = $this->order;

        if (!$order) {
            return null;
        }

        $debit = self::accountFor($this->method)?->id;
        $services = Account::findByCode("7.2")?->id;
        $amount = Money::round($this->amount);
        $description = __("app.payment") . " " . $order->order_number;
        $date = $this->paid_at->toDateString();

        if ($order->is_credit) {
            return JournalEntry::postPair(
                $debit,
                Account::findByCode("4.1.1")?->id,
                $amount,
                $date,
                $description,
                "payment",
                $this->id
            );
        }

        $rate = (float) $order->vat_rate;
        if ($rate > 0 && $services && $debit) {
            if ($vatAccount = Account::findByCode("4.4.3.3")?->id) {
                [$net, $vatPart] = $order->splitGrossAmount($amount);

                $entry = JournalEntry::postLines([
                    ["account_id" => $debit, "debit" => $amount, "credit" => 0],
                    ["account_id" => $services, "debit" => 0, "credit" => $net],
                    ["account_id" => $vatAccount, "debit" => 0, "credit" => $vatPart],
                ], $date, $description, "payment", $this->id);

                if ($entry) {
                    return $entry;
                }
            }
        }

        return JournalEntry::postPair($debit, $services, $amount, $date, $description, "payment", $this->id);
    }
}
