<?php

namespace App\Services;

use App\Actions\Orders\UpdateOrder;
use App\Models\AccountingPeriod;
use App\Models\CashSession;
use App\Models\CourierJob;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\WhatsApp\AutomaticNotifications;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Home pickup & delivery.
 *
 * - Every trip charges its zone fee, unless the order (before VAT) reaches the
 *   zone's free threshold. The fee joins the order's subtotal (taxed service revenue),
 *   like the express surcharge: Order::delivery_fee = sum of its non-cancelled trips.
 * - A pickup has no order yet; its fee is billed when the order is created from it.
 * - On delivery the courier collects the open balance. Cash stays "with the courier"
 *   (no till) until it is handed over at the counter; electronic payments are final.
 */
class Logistics
{
    public function __construct(
        private AutomaticNotifications $notifications,
        private UpdateOrder $updateOrder,
    ) {}

    // ---- Scheduling ----

    /** Pickup booked at the counter (status scheduled) or requested on the website (requested). */
    public function schedulePickup(array $data, ?User $user, string $source = "counter"): CourierJob
    {
        $zone = !empty($data["delivery_zone_id"]) ? DeliveryZone::find($data["delivery_zone_id"]) : null;
        $customer = !empty($data["customer_id"]) ? Customer::find($data["customer_id"]) : null;

        $job = CourierJob::create([
            "type" => "pickup",
            "status" => $source === "website" ? "requested" : "scheduled",
            "source" => $source,
            "customer_id" => $customer?->id,
            "delivery_zone_id" => $zone?->id,
            "contact_name" => $data["contact_name"] ?? $customer?->name,
            "contact_phone" => $data["contact_phone"] ?? $customer?->phone,
            "address" => $data["address"] ?? $customer?->address,
            "address_reference" => $data["address_reference"] ?? $customer?->address_reference,
            "scheduled_date" => $data["scheduled_date"],
            "time_window" => $data["time_window"] ?? "any",
            "courier_id" => $data["courier_id"] ?? null,
            "fee" => array_key_exists("fee", $data) && $data["fee"] !== null ? (float) $data["fee"] : ($zone?->feeFor() ?? 0),
            "notes" => $data["notes"] ?? null,
            "created_by" => $user?->id,
        ]);

        if ($job->status === "scheduled") {
            $this->notifications->pickupScheduled($job);
        }

        return $job;
    }

    /** Delivery of an existing order to the customer's address; the fee joins the order at once. */
    public function scheduleDelivery(Order $order, array $data, ?User $user): CourierJob
    {
        if ($order->status === "completed" || $order->abandoned_at) {
            throw ValidationException::withMessages(["order_id" => __("app.delivery_order_closed")]);
        }

        if ($order->courierJobs()->where("type", "delivery")->open()->exists()) {
            throw ValidationException::withMessages(["order_id" => __("app.delivery_already_scheduled")]);
        }

        $zone = !empty($data["delivery_zone_id"]) ? DeliveryZone::find($data["delivery_zone_id"]) : null;
        $customer = $order->customer;

        return DB::transaction(function () use ($order, $data, $user, $zone, $customer) {
            $job = CourierJob::create([
                "type" => "delivery",
                "status" => "scheduled",
                "source" => "counter",
                "customer_id" => $customer?->id,
                "order_id" => $order->id,
                "delivery_zone_id" => $zone?->id,
                "contact_name" => $data["contact_name"] ?? $customer?->name,
                "contact_phone" => $data["contact_phone"] ?? $customer?->phone,
                "address" => $data["address"] ?? $customer?->address,
                "address_reference" => $data["address_reference"] ?? $customer?->address_reference,
                "scheduled_date" => $data["scheduled_date"],
                "time_window" => $data["time_window"] ?? "any",
                "courier_id" => $data["courier_id"] ?? null,
                "fee" => array_key_exists("fee", $data) && $data["fee"] !== null
                    ? (float) $data["fee"]
                    : ($zone?->feeFor($order->itemsSubtotal()) ?? 0),
                "notes" => $data["notes"] ?? null,
                "created_by" => $user?->id,
            ]);

            $order->recalculateTotals();

            return $job;
        });
    }

    /**
     * Website request accepted: linked to the customer with that phone, or a new
     * customer is registered with the details the visitor gave.
     */
    public function confirm(CourierJob $job, array $data, User $user): CourierJob
    {
        if ($job->status !== "requested") {
            throw ValidationException::withMessages(["status" => __("app.courier_job_not_requested")]);
        }

        return DB::transaction(function () use ($job, $data, $user) {
            $customer = !empty($data["customer_id"])
                ? Customer::findOrFail($data["customer_id"])
                : (Customer::findByPhone($job->contact_phone) ?? Customer::create([
                    "name" => $job->contact_name,
                    "phone" => $job->contact_phone,
                    "address" => $job->address,
                    "address_reference" => $job->address_reference,
                    "delivery_zone_id" => $job->delivery_zone_id,
                ]));

            $job->update(array_merge(
                array_intersect_key($data, array_flip(["scheduled_date", "time_window", "courier_id", "fee"])),
                ["status" => "scheduled", "customer_id" => $customer->id, "created_by" => $job->created_by ?? $user->id],
            ));

            $this->notifications->pickupScheduled($job);

            return $job;
        });
    }

    /** Date, window, courier, address or fee changes on a job that has not started. */
    public function update(CourierJob $job, array $data): CourierJob
    {
        if (!in_array($job->status, ["requested", "scheduled", "failed"], true)) {
            throw ValidationException::withMessages(["status" => __("app.courier_job_locked")]);
        }

        $dateChanged = isset($data["scheduled_date"]) && $data["scheduled_date"] !== $job->scheduled_date->toDateString();

        DB::transaction(function () use ($job, $data) {
            $job->update($data + ($job->status === "failed" ? ["status" => "scheduled", "failure_reason" => null] : []));

            if ($job->isDelivery() && $job->order && array_key_exists("fee", $data)) {
                $job->order->recalculateTotals();
            }
        });

        if ($job->isPickup() && $job->status === "scheduled" && $dateChanged) {
            $this->notifications->pickupScheduled($job);
        }

        return $job;
    }

    public function cancel(CourierJob $job): void
    {
        if (!$job->isOpen()) {
            throw ValidationException::withMessages(["status" => __("app.courier_job_locked")]);
        }

        DB::transaction(function () use ($job) {
            $job->update(["status" => "cancelled", "cancelled_at" => now()]);

            if ($job->order) {
                $order = $job->order;
                $order->recalculateTotals();

                // Never leave the customer having paid more than the order now costs.
                if ($order->remainingBalance() < 0) {
                    throw ValidationException::withMessages(["status" => __("app.cancel_would_overpay")]);
                }
            }
        });
    }

    // ---- Courier actions ----

    /** The courier leaves: the customer is told; a delivered order moves to "delivering". */
    public function start(CourierJob $job, User $user): CourierJob
    {
        $this->assertActionable($job);

        if ($job->isDelivery()) {
            $order = $job->order;

            if (!$order || $order->statusRank() < $order->statusRank("ready")) {
                throw ValidationException::withMessages(["status" => __("app.delivery_order_not_ready")]);
            }

            if ($order->statusRank() < $order->statusRank("delivering")) {
                $this->moveOrder($order, "delivering", $user);
            }
        }

        $job->update(["status" => "on_the_way", "started_at" => now(), "courier_id" => $job->courier_id ?? $user->id]);

        $this->notifications->courierOnTheWay($job);

        return $job;
    }

    /** Clothes collected; the counter then creates the order from this pickup. */
    public function completePickup(CourierJob $job, User $user, ?string $notes = null): CourierJob
    {
        $this->assertActionable($job, "pickup");

        $job->update([
            "status" => "done",
            "completed_at" => now(),
            "outcome_notes" => $notes,
            "courier_id" => $job->courier_id ?? $user->id,
            "started_at" => $job->started_at ?? now(),
        ]);

        return $job;
    }

    /**
     * Order handed to the customer. The courier records what was collected; a
     * non-credit order must be fully paid to be delivered (the counter cannot take
     * payments on a completed cash order).
     *
     * @return array{job: CourierJob, payment: ?Payment, warnings: string[]}
     */
    public function completeDelivery(CourierJob $job, User $user, float $amount = 0, ?string $method = null, ?string $notes = null, ?string $transactionRef = null, ?string $payerPhone = null): array
    {
        $this->assertActionable($job, "delivery");
        $order = $job->order;
        $remaining = $order->remainingBalance();

        if ($order->statusRank() < $order->statusRank("ready")) {
            throw ValidationException::withMessages(["status" => __("app.delivery_order_not_ready")]);
        }

        if ($amount > $remaining + 0.001) {
            throw ValidationException::withMessages(["amount" => __("app.payment_exceeds_remaining", ["amount" => money($remaining)])]);
        }

        if (!$order->is_credit && $remaining - $amount > 0.001) {
            throw ValidationException::withMessages(["amount" => __("app.delivery_requires_full_payment", ["amount" => money($remaining)])]);
        }

        if ($amount > 0) {
            if (!in_array($method, Payment::courierMethods(), true)) {
                throw ValidationException::withMessages(["method" => __("validation.required", ["attribute" => __("app.payment_method")])]);
            }
            AccountingPeriod::assertOpen(today()->toDateString(), "amount");
        }

        // Counter staff recording a delivery take the cash into their own till.
        $session = CashSession::currentFor($user);
        if ($amount > 0 && $method === "cash" && !$session && $user->isCashier()) {
            throw ValidationException::withMessages(["amount" => __("app.cash_session_required")]);
        }

        [$payment, $warnings] = DB::transaction(function () use ($job, $order, $user, $amount, $method, $notes, $session, $transactionRef, $payerPhone) {
            $payment = null;

            if ($amount > 0) {
                $intoTill = $method === "cash" && $session !== null;
                $payment = Payment::create([
                    "order_id" => $order->id,
                    "user_id" => $user->id,
                    "cash_session_id" => $intoTill ? $session->id : null,
                    "paid_at" => today()->toDateString(),
                    "amount" => $amount,
                    "method" => $method,
                    "transaction_ref" => $transactionRef,
                    "payer_phone" => $payerPhone,
                    "note" => __("app.collected_on_delivery"),
                    "invoice_id" => $order->invoice_id,
                    "courier_job_id" => $job->id,
                    // Electronic money is already in the company's account; till cash is counted at close.
                    "handed_over_at" => $method !== "cash" || $intoTill ? now() : null,
                    "handed_over_by" => $intoTill ? $user->id : null,
                ]);
            }

            $job->update([
                "status" => "done",
                "completed_at" => now(),
                "outcome_notes" => $notes,
                "courier_id" => $job->courier_id ?? $user->id,
                "started_at" => $job->started_at ?? now(),
            ]);

            $warnings = $this->moveOrder($order->fresh(), "completed", $user);

            return [$payment, $warnings];
        });

        if ($payment) {
            if ($payment->postToJournal() === null) {
                $warnings[] = __("app.journal_skipped");
            }
            $this->notifications->paymentReceived($payment);
        }

        return ["job" => $job, "payment" => $payment, "warnings" => $warnings];
    }

    public function fail(CourierJob $job, User $user, string $reason): CourierJob
    {
        $this->assertActionable($job);

        $job->update([
            "status" => "failed",
            "failure_reason" => $reason,
            "courier_id" => $job->courier_id ?? $user->id,
        ]);

        return $job;
    }

    // ---- Cash collected by couriers ----

    /** Cash still held by each person who collected it on a delivery. */
    public function cashToHandOver(): Collection
    {
        return Payment::pendingHandover()->with(["user:id,name", "order:id,order_number,customer_id", "order.customer:id,name"])
            ->orderBy("paid_at")->get()->groupBy("user_id");
    }

    /** The counter receives a courier's cash into its open till; the blind count at close checks it. */
    public function receiveCash(User $courier, User $cashier): float
    {
        $session = CashSession::currentFor($cashier);
        if (!$session) {
            throw ValidationException::withMessages(["till" => __("app.cash_session_required")]);
        }

        return DB::transaction(function () use ($courier, $cashier, $session) {
            $payments = Payment::pendingHandover()->where("user_id", $courier->id)->lockForUpdate()->get();

            foreach ($payments as $payment) {
                $payment->update(["cash_session_id" => $session->id, "handed_over_at" => now(), "handed_over_by" => $cashier->id]);
            }

            return (float) $payments->sum("amount");
        });
    }

    // ---- Helpers ----

    /** Fee of a pickup billed on the order created from it (free over the zone threshold). */
    public function pickupFeeFor(CourierJob $pickup, float $itemsSubtotal): float
    {
        $zone = $pickup->zone;

        if ($zone && $zone->free_from !== null && $itemsSubtotal >= (float) $zone->free_from) {
            return 0.0;
        }

        return (float) $pickup->fee;
    }

    private function assertActionable(CourierJob $job, ?string $type = null): void
    {
        if (!$job->isActionable() || ($type !== null && $job->type !== $type)) {
            throw ValidationException::withMessages(["status" => __("app.courier_job_locked")]);
        }
    }

    /** Advances the order through the normal flow, so stock, points and credit postings still happen. */
    private function moveOrder(Order $order, string $status, User $user): array
    {
        $result = $this->updateOrder->handle($order, [
            "customer_id" => $order->customer_id,
            "received_at" => $order->received_at->toDateString(),
            "status" => $status,
            "completed_at" => $status === "completed" ? now() : $order->completed_at,
        ], $user, []);

        if ($result["error"]) {
            throw ValidationException::withMessages(["status" => $result["error"]]);
        }

        return $result["warnings"];
    }
}
