<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Consolidated invoices: draft → issued → (void). Issuing fixes one due date on all
 * the invoice's orders, so aging, overdue blocks and reminders keep working off
 * orders.due_date exactly as for per-order credit sales.
 */
class Invoicing
{
    private const NUMBER_ATTEMPTS = 5;

    /** Completed, unpaid credit orders not yet on an invoice, up to the period end. */
    public function eligibleOrders(Customer $customer, Carbon $periodEnd): Collection
    {
        return $customer->orders()
            ->outstandingCredit()
            ->whereNull("invoice_id")
            ->whereDate("completed_at", "<=", $periodEnd->toDateString())
            ->orderBy("completed_at")
            ->orderBy("id")
            ->get();
    }

    /**
     * @param  int[]|null  $orderIds  restrict to these eligible orders (null = all)
     */
    public function createDraft(Customer $customer, Carbon $start, Carbon $end, ?array $orderIds, ?User $user, ?string $notes = null): Invoice
    {
        if (!$customer->is_credit_customer) {
            throw ValidationException::withMessages(["customer_id" => __("app.not_credit_customer")]);
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages(["period_end" => __("app.invoice_period_invalid")]);
        }

        $orders = $this->eligibleOrders($customer, $end);
        if ($orderIds !== null) {
            $orders = $orders->whereIn("id", array_map("intval", $orderIds));
        }

        if ($orders->isEmpty()) {
            throw ValidationException::withMessages(["orders" => __("app.invoice_no_orders")]);
        }

        return DB::transaction(function () use ($customer, $start, $end, $orders, $user, $notes) {
            $invoice = Invoice::create([
                "customer_id" => $customer->id,
                "period_start" => $start->toDateString(),
                "period_end" => $end->toDateString(),
                "status" => "draft",
                "notes" => $notes,
                "created_by" => $user?->id,
            ]);

            Order::whereKey($orders->modelKeys())->update(["invoice_id" => $invoice->id]);
            $invoice->recalculate();

            return $invoice;
        });
    }

    public function removeOrder(Invoice $invoice, Order $order): void
    {
        if (!$invoice->isDraft() || $order->invoice_id !== $invoice->id) {
            throw ValidationException::withMessages(["invoice" => __("app.invoice_not_draft")]);
        }

        $order->update(["invoice_id" => null]);
        $invoice->recalculate();
    }

    public function issue(Invoice $invoice, ?Carbon $issueDate = null): Invoice
    {
        if (!$invoice->isDraft()) {
            throw ValidationException::withMessages(["invoice" => __("app.invoice_not_draft")]);
        }

        $invoice->load("orders", "customer");
        if ($invoice->orders->isEmpty()) {
            throw ValidationException::withMessages(["invoice" => __("app.invoice_no_orders")]);
        }

        $issueDate ??= today();
        $dueDate = $issueDate->copy()->addDays((int) ($invoice->customer->credit_terms_days ?: 30));

        for ($attempt = 1; ; $attempt++) {
            try {
                DB::transaction(function () use ($invoice, $issueDate, $dueDate) {
                    $invoice->recalculate();
                    $invoice->update([
                        "number" => Invoice::nextNumber($issueDate->year),
                        "status" => "issued",
                        "issue_date" => $issueDate->toDateString(),
                        "due_date" => $dueDate->toDateString(),
                        "issued_at" => now(),
                    ]);

                    Order::where("invoice_id", $invoice->id)->update(["due_date" => $dueDate->toDateString()]);
                });

                return $invoice->refresh();
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::NUMBER_ATTEMPTS) {
                    throw $e;
                }
            }
        }
    }

    /**
     * A draft is simply discarded. An issued invoice is kept (its number cannot be
     * reused) but marked void; its orders go back to being unbilled with the due
     * date they would have had on their own.
     */
    public function void(Invoice $invoice, string $reason): void
    {
        if ($invoice->isVoid()) {
            return;
        }

        DB::transaction(function () use ($invoice, $reason) {
            $invoice->load("orders", "customer");

            foreach ($invoice->orders as $order) {
                $order->update([
                    "invoice_id" => null,
                    "due_date" => $this->standaloneDueDate($invoice->customer, $order),
                ]);
            }

            if ($invoice->isDraft()) {
                $invoice->delete();

                return;
            }

            $invoice->update(["status" => "void", "voided_at" => now(), "void_reason" => $reason]);
        });
    }

    /**
     * Drafts for every monthly-billed customer with unbilled orders in the month.
     *
     * @return Collection<int, Invoice>
     */
    public function generateMonthly(Carbon $month, ?User $user = null): Collection
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $created = new Collection;

        Customer::where("is_credit_customer", true)
            ->where("billing_cycle", "monthly")
            ->orderBy("name")
            ->each(function (Customer $customer) use ($start, $end, $user, $created) {
                if ($this->eligibleOrders($customer, $end)->isEmpty()) {
                    return;
                }

                $created->push($this->createDraft($customer, $start, $end, null, $user));
            });

        return $created;
    }

    /** Monthly customers wait for their invoice; others pay within their terms. */
    private function standaloneDueDate(Customer $customer, Order $order): ?string
    {
        if ($customer->billsMonthly()) {
            return null;
        }

        $base = $order->completed_at ?? $order->delivery_date ?? $order->received_at;

        return $base?->copy()->addDays((int) ($customer->credit_terms_days ?: 30))->toDateString();
    }
}
