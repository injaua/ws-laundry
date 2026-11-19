<?php

namespace App\Services\MobileMoney;

use App\Actions\Payments\SettleOrderStatus;
use App\Jobs\ProcessMobileTransaction;
use App\Models\CourierJob;
use App\Models\CreditNote;
use App\Models\MobileTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\WhatsApp\AutomaticNotifications;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Push payments (C2B) and refunds (B2C) through the operators' APIs.
 *
 * The request is answered right away ("waiting for the customer's PIN") and the
 * operator call runs after the response; the page polls the status. A confirmed
 * collection becomes a normal payment (reference, journal, order status, receipt).
 */
class MobilePayments
{
    public function __construct(
        private SettleOrderStatus $settle,
        private AutomaticNotifications $notifications,
    ) {}

    public function request(Order $order, string $provider, string $phone, ?float $amount, ?User $user, string $channel = "counter", ?CourierJob $job = null): MobileTransaction
    {
        if (!in_array($provider, MobileTransaction::PROVIDERS, true) || !MobileMoneySettings::enabled($provider)) {
            throw ValidationException::withMessages(["provider" => __("app.mm_provider_disabled")]);
        }

        if ($order->abandoned_at || ($order->status === "completed" && !$order->is_credit)) {
            throw ValidationException::withMessages(["amount" => __("app.payment_locked_error")]);
        }

        $remaining = $order->remainingBalance();
        if (!Money::isPositive($remaining)) {
            throw ValidationException::withMessages(["amount" => __("app.order_fully_paid")]);
        }

        // Wallets charge whole meticais: the full balance is pushed without its cents
        // (paid at the counter), and a typed amount with cents is refused.
        if ($amount !== null && !self::isWholeAmount($amount)) {
            throw ValidationException::withMessages(["amount" => __("app.mm_whole_amount")]);
        }
        $amount = $amount === null ? floor($remaining) : Money::round($amount);
        if (Money::compare($amount, 1) < 0 || Money::compare($amount, $remaining) > 0) {
            throw ValidationException::withMessages(["amount" => __("app.payment_exceeds_remaining", ["amount" => money($remaining)])]);
        }

        $normalized = MobileTransaction::normalizePhone($phone);
        if (!$normalized) {
            throw ValidationException::withMessages(["phone" => __("app.mm_invalid_phone")]);
        }
        if (MobileTransaction::providerForPhone($normalized) !== $provider) {
            throw ValidationException::withMessages(["phone" => __("app.mm_phone_wrong_network", ["provider" => __("app.$provider")])]);
        }

        // One open request per order: settle stale ones first.
        foreach (MobileTransaction::open()->where("order_id", $order->id)->where("type", "c2b")->get() as $open) {
            $this->refresh($open);
        }
        if (MobileTransaction::open()->where("order_id", $order->id)->where("type", "c2b")->exists()) {
            throw ValidationException::withMessages(["phone" => __("app.mm_request_open")]);
        }

        $tx = MobileTransaction::create([
            "type" => "c2b",
            "provider" => $provider,
            "reference" => MobileTransaction::newReference("c2b"),
            "order_id" => $order->id,
            "customer_id" => $order->customer_id,
            "courier_job_id" => $job?->id,
            "user_id" => $user?->id,
            "channel" => $channel,
            "phone" => $normalized,
            "amount" => $amount,
            "status" => "pending",
        ]);

        ProcessMobileTransaction::dispatchAfterResponse($tx->id);

        return $tx;
    }

    /** Refund of a credit note straight to the customer's wallet. */
    public function refund(CreditNote $creditNote, string $phone, User $user): MobileTransaction
    {
        $provider = $creditNote->refund_method;

        if (!$creditNote->isRefund() || $creditNote->isVoided() || !in_array($provider, MobileTransaction::PROVIDERS, true) || !MobileMoneySettings::enabled($provider)) {
            throw ValidationException::withMessages(["phone" => __("app.mm_refund_not_allowed")]);
        }

        if (MobileTransaction::where("credit_note_id", $creditNote->id)->where("type", "b2c")->whereIn("status", ["pending", "processing", "success"])->exists()) {
            throw ValidationException::withMessages(["phone" => __("app.mm_refund_exists")]);
        }

        $normalized = MobileTransaction::normalizePhone($phone);
        if (!$normalized || MobileTransaction::providerForPhone($normalized) !== $provider) {
            throw ValidationException::withMessages(["phone" => __("app.mm_phone_wrong_network", ["provider" => __("app.$provider")])]);
        }

        if (!self::isWholeAmount($creditNote->amount)) {
            throw ValidationException::withMessages(["amount" => __("app.mm_whole_amount")]);
        }

        $tx = MobileTransaction::create([
            "type" => "b2c",
            "provider" => $provider,
            "reference" => MobileTransaction::newReference("b2c"),
            "order_id" => $creditNote->order_id,
            "customer_id" => $creditNote->customer_id,
            "credit_note_id" => $creditNote->id,
            "user_id" => $user->id,
            "channel" => "counter",
            "phone" => $normalized,
            "amount" => (float) $creditNote->amount,
            "status" => "pending",
        ]);

        ProcessMobileTransaction::dispatchAfterResponse($tx->id);

        return $tx;
    }

    /** M-Pesa and e-Mola only move whole meticais. */
    public static function isWholeAmount(float|int|string|null $amount): bool
    {
        $amount = Money::round($amount);

        return Money::equals($amount, round($amount));
    }

    /** Sends the request to the operator (runs after the HTTP response). */
    public function process(MobileTransaction $tx): MobileTransaction
    {
        if ($tx->status !== "pending") {
            return $tx;
        }

        $gateway = MobileMoneySettings::gateway($tx->provider);
        if (!$gateway) {
            $tx->update(["status" => "failed", "response_code" => "DISABLED", "response_message" => __("app.mm_provider_disabled")]);

            return $tx;
        }

        $tx->update(["status" => "processing", "attempts" => $tx->attempts + 1]);

        try {
            $result = $tx->type === "b2c" ? $gateway->refund($tx) : $gateway->collect($tx);
        } catch (Throwable $e) {
            Log::warning("[mobile-money] {$tx->reference}: " . $e->getMessage());
            $result = GatewayResult::failed("ERROR", $e->getMessage());
        }

        return $this->apply($tx, $result);
    }

    /** Asks the operator about a request without a final answer; expires old ones. */
    public function refresh(MobileTransaction $tx): MobileTransaction
    {
        if (!$tx->isOpen()) {
            return $tx;
        }

        if ($tx->status === "pending") {
            return $this->process($tx);
        }

        if ($gateway = MobileMoneySettings::gateway($tx->provider)) {
            try {
                $this->apply($tx, $gateway->status($tx));
            } catch (Throwable $e) {
                Log::warning("[mobile-money] status {$tx->reference}: " . $e->getMessage());
            }
        }

        $tx->refresh();
        if ($tx->isOpen() && $tx->created_at->lt(now()->subMinutes(MobileTransaction::EXPIRE_MINUTES))) {
            $tx->update(["status" => "expired", "response_message" => $tx->response_message ?: __("app.mm_expired")]);
        }

        return $tx;
    }

    public function apply(MobileTransaction $tx, GatewayResult $result): MobileTransaction
    {
        $base = [
            "response_code" => $result->code,
            "response_message" => $result->message ? mb_substr($result->message, 0, 255) : null,
            "raw" => $result->raw ?: $tx->raw,
            "conversation_id" => $result->conversationId ?? $tx->conversation_id,
        ];

        match ($result->status) {
            GatewayResult::SUCCESS => $tx->update($base + [
                "status" => "success",
                "provider_ref" => $result->providerRef ?? $tx->provider_ref,
                "completed_at" => now(),
            ]),
            GatewayResult::FAILED => $tx->update($base + ["status" => "failed", "completed_at" => now()]),
            default => $tx->update($base + ["status" => "processing"]),
        };

        if ($tx->status === "success" && $tx->type === "c2b") {
            $this->recordPayment($tx);
        }

        return $tx->fresh();
    }

    /** A confirmed collection becomes the order's payment (once). */
    private function recordPayment(MobileTransaction $tx): ?Payment
    {
        $payment = DB::transaction(function () use ($tx) {
            $tx = MobileTransaction::lockForUpdate()->find($tx->id);
            if ($tx->payment_id || !$tx->order) {
                return null;
            }

            $order = $tx->order;
            // Paid meanwhile by other means: record what is still owed and flag the rest.
            $amount = Money::min($tx->amount, Money::max(0, $order->remainingBalance()));
            if (!Money::isPositive($amount)) {
                $tx->update(["response_message" => __("app.mm_overpaid", ["amount" => money((float) $tx->amount)])]);

                return null;
            }

            $payment = Payment::create([
                "order_id" => $order->id,
                "user_id" => $tx->user_id,
                "cash_session_id" => null, // electronic money: verified by the operator, not counted in the till
                "paid_at" => today()->toDateString(),
                "amount" => $amount,
                "method" => $tx->provider,
                "transaction_ref" => Payment::normalizeRef($tx->provider_ref ?: $tx->reference),
                "payer_phone" => $tx->phone,
                "note" => __("app.mm_payment_note", ["ref" => $tx->reference]),
                "invoice_id" => $order->invoice_id,
                "courier_job_id" => $tx->courier_job_id,
                "handed_over_at" => $tx->courier_job_id ? now() : null,
            ]);

            if (Money::compare($amount, $tx->amount) < 0) {
                $tx->update(["response_message" => __("app.mm_overpaid", ["amount" => money(Money::sub($tx->amount, $amount))])]);
            }

            $tx->update(["payment_id" => $payment->id]);

            return $payment;
        });

        if ($payment) {
            $payment->postToJournal();
            $this->settle->handle($payment->order, $tx->user);
            $this->notifications->paymentReceived($payment);
        }

        return $payment;
    }
}
