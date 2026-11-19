<?php

namespace App\Actions\Orders;

use App\Models\AccountingPeriod;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderConsumption;
use App\Services\PieceTracker;
use App\Services\Production;
use App\Services\WhatsApp\AutomaticNotifications;

/**
 * Applies an order update: enforces the forward-only status flow and the cashier
 * "status only" rule, then runs the side effects of reaching a stage (stock
 * consumption, loyalty points, credit-sale journal entry).
 */
class UpdateOrder
{
    public function __construct(
        private OrderConsumption $consumption,
        private AutomaticNotifications $notifications,
        private PieceTracker $pieces,
    ) {}

    /**
     * @return array{error: ?string, warnings: string[]}
     */
    public function handle(Order $order, array $data, User $user, array $input): array
    {
        if ($order->status === "completed") {
            return ["error" => __("app.order_locked_error"), "warnings" => []];
        }

        // Single flow: the clothing state may only move forward, received -> completed
        if (!$order->canTransitionTo($data["status"])) {
            return ["error" => __("app.status_cannot_go_back"), "warnings" => []];
        }

        // Quality control (when required) is the only way into "ready": nothing skips it.
        if (Production::qcRequired() && !$order->qc_passed_at
            && $order->statusRank() < $order->statusRank("ready")
            && $order->statusRank($data["status"]) >= $order->statusRank("ready")) {
            return ["error" => __("app.qc_required_error"), "warnings" => []];
        }

        // Store (cashier) accounts may only advance the order status, never alter its data
        if ($user->isCashier()) {
            $changesData = (int) $data["customer_id"] !== (int) $order->customer_id
                || now()->parse($data["received_at"])->toDateString() !== $order->received_at->toDateString()
                || (array_key_exists("notes", $input) && $input["notes"] !== $order->notes);

            if ($changesData) {
                return ["error" => __("app.cashier_status_only"), "warnings" => []];
            }

            $order->update(["status" => $data["status"], "completed_at" => $data["completed_at"] ?? null]);
        } else {
            $order->update($data);
        }

        $warnings = [];

        // Delivering with garments that never reached "ready" usually means one is missing.
        if ($order->wasChanged("status") && in_array($order->status, ["delivering", "completed"], true)
            && ($missing = $this->pieces->notReady($order))->isNotEmpty()) {
            $warnings[] = __("app.pieces_not_ready_warning", [
                "count" => $missing->count(),
                "codes" => $missing->take(5)->pluck("code")->join(", ") . ($missing->count() > 5 ? "…" : ""),
            ]);
        }

        if ($order->wasChanged("status") && $order->status === "ready") {
            $this->notifications->orderReady($order);
        }

        // Consume the recipes of every stage the order has just reached (forward
        // jumps consume skipped stages). Stock is never reverted: the work already
        // happened physically.
        $result = $this->consumption->consumeIfNeeded($order, $user);
        if ($shortfall = $this->consumption->shortfallMessage($result)) {
            $warnings[] = $shortfall;
        }

        if ($order->status === "completed") {
            $this->pieces->orderCompleted($order, $user);
            $order->customer->earnPoints($order);

            if ($order->is_credit && $journalWarning = $this->postCreditSale($order)) {
                $warnings[] = $journalWarning;
            }
        }

        return ["error" => null, "warnings" => $warnings];
    }

    /** Revenue on credit sales is recognised on delivery: D 4.1.1 / C 7.2 + IVA. */
    private function postCreditSale(Order $order): ?string
    {
        if ($period = AccountingPeriod::closedOn($order->completed_at ?? now())) {
            return __("app.period_closed", ["period" => $period->label()]);
        }

        return $order->postCreditSaleToJournal() === null ? __("app.journal_skipped") : null;
    }
}
