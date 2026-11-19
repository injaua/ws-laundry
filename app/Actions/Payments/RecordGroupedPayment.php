<?php

namespace App\Actions\Payments;

use App\Models\AccountingPeriod;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\Cheques;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * One payment from a business customer (typically a bank transfer) settling several
 * credit orders. It is split oldest-first into ordinary per-order Payment rows sharing
 * a group reference, so each keeps its own journal entry (D 1.1/1.2.1 / C 4.1.1) and
 * every per-order balance, report and reversal keeps working unchanged.
 */
class RecordGroupedPayment
{
    public function __construct(private AutomaticNotifications $notifications) {}

    /**
     * @param  array{paid_at: string, amount: float|string, method: string, note?: ?string}  $data
     * @return array{group_ref: string, payments: Collection<int, Payment>, journal_skipped: bool}
     */
    public function handle(Customer $customer, array $data, User $user, ?Invoice $invoice = null): array
    {
        AccountingPeriod::assertOpen($data["paid_at"], "paid_at");

        if ($invoice && (!$invoice->isIssued() || $invoice->customer_id !== $customer->id)) {
            throw ValidationException::withMessages(["amount" => __("app.invoice_not_payable")]);
        }

        $orders = $this->openOrders($customer, $invoice);
        $outstanding = Money::sum($orders->map(fn (Order $order) => $order->remainingBalance()));
        $amount = Money::round((float) $data["amount"]);

        if (!Money::isPositive($outstanding)) {
            throw ValidationException::withMessages(["amount" => __("app.nothing_outstanding")]);
        }

        if (Money::compare($amount, $outstanding) > 0) {
            throw ValidationException::withMessages(["amount" => __("app.payment_exceeds_remaining", [
                "amount" => money($outstanding),
            ])]);
        }

        Cheques::assertAccepted($customer, $data["method"] ?? null);

        $session = CashSession::currentFor($user);
        if (!$session && $user->isCashier()) {
            throw ValidationException::withMessages(["amount" => __("app.cash_session_required")]);
        }

        $groupRef = "PG-" . now()->format("ymd") . "-" . Str::upper(Str::random(5));

        $payments = DB::transaction(function () use ($orders, $amount, $data, $user, $session, $groupRef, $customer) {
            $left = $amount;
            $payments = collect();

            foreach ($orders as $order) {
                if (!Money::isPositive($left)) {
                    break;
                }

                $share = Money::min($left, $order->remainingBalance());
                if (!Money::isPositive($share)) {
                    continue;
                }

                $payments->push(Payment::create([
                    "order_id" => $order->id,
                    "user_id" => $user->id,
                    "cash_session_id" => $session?->id,
                    "paid_at" => $data["paid_at"],
                    "amount" => $share,
                    "method" => $data["method"],
                    "transaction_ref" => $data["transaction_ref"] ?? null,
                    "payer_phone" => $data["payer_phone"] ?? null,
                    "note" => $data["note"] ?? null,
                    "group_ref" => $groupRef,
                    "invoice_id" => $order->invoice_id,
                ]));

                $left = Money::sub($left, $share);
            }

            // One cheque settling several orders: a single cheque shared by the payments.
            if (($data["method"] ?? null) === "cheque" && $payments->isNotEmpty()) {
                app(Cheques::class)->attach($payments, $data, $customer, $user);
            }

            return $payments;
        });

        $journalSkipped = false;
        foreach ($payments as $payment) {
            $journalSkipped = $payment->postToJournal() === null || $journalSkipped;
        }

        $this->notifications->groupedPaymentReceived($payments, $invoice);

        return ["group_ref" => $groupRef, "payments" => $payments, "journal_skipped" => $journalSkipped];
    }

    /** Oldest debt first: earliest due date, then earliest completion. */
    public function openOrders(Customer $customer, ?Invoice $invoice = null): Collection
    {
        $query = $invoice ? $invoice->orders()->getQuery() : $customer->orders()->getQuery();

        return $query->outstandingCredit()
            ->reorder()
            ->orderByRaw("due_date is null")
            ->orderBy("due_date")
            ->orderBy("completed_at")
            ->orderBy("id")
            ->get();
    }
}
