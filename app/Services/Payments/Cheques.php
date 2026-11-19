<?php

namespace App\Services\Payments;

use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\BankDeposit;
use App\Models\Cheque;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cheques and bank deposits.
 *
 * Ledger: receiving a cheque debits 1.3 Cheques a depositar (Payment::postToJournal).
 * A bank deposit moves cash (C 1.1) and cheques (C 1.3) to D 1.2.1 Depósitos à ordem.
 * A bounce mirrors the payment entries, crediting wherever the cheque sits at that
 * moment (1.3 in hand, 1.2.1 once deposited), so revenue / receivables come back.
 */
class Cheques
{
    /** May this customer pay by cheque? (business policy + no bounced cheque before) */
    public static function acceptedFrom(?Customer $customer): bool
    {
        return match (Cheque::policy()) {
            "off" => false,
            "business" => $customer?->isBusiness() === true && !self::blocked($customer),
            default => $customer === null || !self::blocked($customer),
        };
    }

    public static function blocked(?Customer $customer): bool
    {
        return $customer !== null && Cheque::where("customer_id", $customer->id)->where("status", "bounced")->exists();
    }

    /** @throws ValidationException */
    public static function assertAccepted(?Customer $customer, ?string $method): void
    {
        if ($method !== "cheque" || self::acceptedFrom($customer)) {
            return;
        }

        $message = match (true) {
            Cheque::policy() === "off" => __("app.cheque_not_accepted"),
            self::blocked($customer) => __("app.cheque_customer_blocked"),
            default => __("app.cheque_business_only"),
        };

        throw ValidationException::withMessages(["method" => $message]);
    }

    /** Creates the cheque for freshly recorded payments (one cheque may cover several orders). */
    public function attach(Collection|Payment $payments, array $data, ?Customer $customer, User $user): Cheque
    {
        $payments = $payments instanceof Payment ? collect([$payments]) : $payments;

        $cheque = Cheque::create([
            "customer_id" => $customer?->id,
            "number" => Cheque::normalizeNumber($data["transaction_ref"] ?? null),
            "bank" => Cheque::normalizeBank($data["cheque_bank"] ?? null),
            "drawer" => $data["cheque_drawer"] ?? null,
            "cheque_date" => $data["cheque_date"] ?? $data["paid_at"],
            "amount" => round((float) $payments->sum("amount"), 2),
            "status" => "in_hand",
            "user_id" => $user->id,
        ]);

        Payment::whereIn("id", $payments->pluck("id"))->update(["cheque_id" => $cheque->id]);

        return $cheque;
    }

    /** A payment linked to a cheque that left the safe can no longer change. */
    public static function assertEditable(Payment $payment): void
    {
        if ($payment->cheque_id && $payment->cheque && !$payment->cheque->isInHand()) {
            throw ValidationException::withMessages(["method" => __("app.cheque_locked", ["status" => __("app.cheque_status_" . $payment->cheque->status)])]);
        }
    }

    /** Keeps the cheque in step after a payment was edited (or its method changed). */
    public function syncAfterUpdate(Payment $payment, array $data, User $user): void
    {
        $old = $payment->cheque_id ? Cheque::find($payment->cheque_id) : null;

        if ($payment->method !== "cheque") {
            if ($old) {
                $payment->update(["cheque_id" => null]);
                $this->refreshOrDelete($old);
            }

            return;
        }

        if (!$old) {
            $this->attach($payment, $data + ["paid_at" => $payment->paid_at->toDateString()], $payment->order?->customer, $user);

            return;
        }

        $old->update(array_filter([
            "number" => Cheque::normalizeNumber($data["transaction_ref"] ?? $old->number),
            "bank" => Cheque::normalizeBank($data["cheque_bank"] ?? $old->bank),
            "drawer" => $data["cheque_drawer"] ?? $old->drawer,
            "cheque_date" => $data["cheque_date"] ?? $old->cheque_date,
        ], fn ($v) => $v !== null && $v !== ""));
        $old->refreshAmount();
    }

    /** After a payment was deleted. */
    public function refreshOrDelete(Cheque $cheque): void
    {
        if ($cheque->allPayments()->exists()) {
            $cheque->refreshAmount();
        } else {
            $cheque->delete();
        }
    }

    /**
     * Cheques that can go to the bank: in hand, and no longer in an open till (the
     * operator still has to count them when closing).
     */
    public function depositable(): Collection
    {
        return Cheque::inHand()
            ->whereDoesntHave("payments.cashSession", fn ($q) => $q->where("status", "open"))
            ->with("customer:id,name")
            ->orderBy("cheque_date")->orderBy("id")
            ->get();
    }

    /**
     * Records a bank deposit slip: cash from the safe plus the chosen cheques.
     *
     * @param  array{deposited_at: string, slip_number?: ?string, cash_amount?: float|string|null, cheque_ids?: array<int>, note?: ?string}  $data
     * @return array{deposit: BankDeposit, warning: ?string}
     */
    public function deposit(array $data, User $user): array
    {
        AccountingPeriod::assertOpen($data["deposited_at"], "deposited_at");

        return DB::transaction(function () use ($data, $user) {
            $ids = array_map("intval", $data["cheque_ids"] ?? []);
            $cheques = Cheque::whereIn("id", $ids)->lockForUpdate()->get();
            $allowed = $this->depositable()->pluck("id")->all();

            foreach ($cheques as $cheque) {
                if (!in_array($cheque->id, $allowed, true)) {
                    throw ValidationException::withMessages(["cheque_ids" => __("app.cheque_not_depositable", ["cheque" => $cheque->label()])]);
                }
                if (!$cheque->canBeDepositedOn($data["deposited_at"])) {
                    throw ValidationException::withMessages(["cheque_ids" => __("app.cheque_post_dated", ["cheque" => $cheque->label(), "date" => $cheque->cheque_date->format(date_fmt())])]);
                }
            }

            $cash = round((float) ($data["cash_amount"] ?? 0), 2);
            $chequesTotal = round((float) $cheques->sum("amount"), 2);
            if ($cash + $chequesTotal <= 0) {
                throw ValidationException::withMessages(["cash_amount" => __("app.deposit_empty")]);
            }

            $deposit = BankDeposit::create([
                "deposited_at" => $data["deposited_at"],
                "slip_number" => Payment::normalizeRef($data["slip_number"] ?? null),
                "cash_amount" => $cash,
                "cheques_amount" => $chequesTotal,
                "total" => $cash + $chequesTotal,
                "note" => $data["note"] ?? null,
                "user_id" => $user->id,
            ]);

            Cheque::whereIn("id", $cheques->pluck("id"))->update([
                "status" => "deposited",
                "bank_deposit_id" => $deposit->id,
                "deposited_at" => now(),
            ]);

            $entry = JournalEntry::postLines([
                ["account_id" => Account::findByCode("1.2.1")?->id, "debit" => $cash + $chequesTotal, "credit" => 0],
                ["account_id" => Account::findByCode("1.1")?->id, "debit" => 0, "credit" => $cash],
                ["account_id" => Account::findByCode("1.3")?->id, "debit" => 0, "credit" => $chequesTotal],
            ], $deposit->deposited_at->toDateString(), __("app.bank_deposit") . " " . $deposit->label() . ($deposit->slip_number ? " ({$deposit->slip_number})" : ""), "bank_deposit", $deposit->id);

            return ["deposit" => $deposit, "warning" => $entry ? null : __("app.journal_skipped")];
        });
    }

    /** The bank paid the cheque: nothing else can happen to it. */
    public function clear(Cheque $cheque): void
    {
        if ($cheque->status !== "deposited") {
            throw ValidationException::withMessages(["cheque" => __("app.cheque_clear_needs_deposit")]);
        }

        $cheque->update(["status" => "cleared", "cleared_at" => now()]);
    }

    /**
     * The cheque was returned unpaid: its payments are cancelled (the orders owe the
     * money again), the ledger is reversed and the customer can no longer pay by cheque.
     *
     * @return array{orders: Collection, warning: ?string}
     */
    public function bounce(Cheque $cheque, string $reason, string $date, User $user): array
    {
        if (!$cheque->canBounce()) {
            throw ValidationException::withMessages(["reason" => __("app.cheque_cannot_bounce")]);
        }
        AccountingPeriod::assertOpen($date, "bounced_on");

        return DB::transaction(function () use ($cheque, $reason, $date) {
            $cheque = Cheque::whereKey($cheque->id)->lockForUpdate()->firstOrFail();
            $payments = $cheque->payments()->with("order")->get();

            $holding = Account::findByCode($cheque->status === "deposited" ? "1.2.1" : "1.3")?->id;
            $chequeAccount = Account::findByCode("1.3")?->id;
            $lines = [];
            foreach ($payments as $payment) {
                $entry = JournalEntry::where("source", "payment")->where("source_id", $payment->id)->with("lines")->first();
                foreach ($entry?->lines ?? [] as $line) {
                    $lines[] = [
                        "account_id" => $line->account_id === $chequeAccount ? $holding : $line->account_id,
                        "debit" => (float) $line->credit,
                        "credit" => (float) $line->debit,
                    ];
                }
            }

            $journal = $lines ? JournalEntry::postLines($lines, Carbon::parse($date)->toDateString(), __("app.cheque_bounced_entry", ["cheque" => $cheque->label()]), "cheque_bounce", $cheque->id) : null;

            Payment::whereIn("id", $payments->pluck("id"))->update(["bounced_at" => now()]);
            $cheque->update(["status" => "bounced", "bounced_at" => Carbon::parse($date), "bounce_reason" => $reason]);

            $hadEntries = $payments->contains(fn ($p) => JournalEntry::where("source", "payment")->where("source_id", $p->id)->exists());

            return [
                "orders" => $payments->pluck("order")->filter()->unique("id")->values(),
                "warning" => $hadEntries && !$journal ? __("app.journal_skipped") : null,
            ];
        });
    }
}
