<?php

namespace App\Services;

use App\Actions\Orders\UpdateOrder;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPieceEvent;
use App\Models\OrderRework;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\WhatsApp\AutomaticNotifications;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Production floor actions: move an order to its next stage (recording who did the
 * work), quality control before "ready", rework (the only way back in the flow),
 * new promise dates for late orders and free re-wash orders from complaints.
 */
class Production
{
    public function __construct(
        private UpdateOrder $updateOrder,
        private AutomaticNotifications $notifications,
    ) {}

    public static function qcRequired(): bool
    {
        return (bool) SiteSetting::get("qc_required", false);
    }

    /** Next production stage of an order, or null when it is already ready or beyond. */
    public function nextStage(Order $order): ?string
    {
        $rank = $order->statusRank();
        $ready = $order->statusRank("ready");

        return $rank < $ready ? Order::STATUSES[$rank + 1] : null;
    }

    /** @return string[] warnings */
    public function advance(Order $order, User $user, ?int $employeeId = null, ?string $to = null): array
    {
        $to ??= $this->nextStage($order);

        if ($to === null) {
            throw ValidationException::withMessages(["status" => __("app.production_nothing_to_advance")]);
        }

        if ($to === "ready" && self::qcRequired() && !$order->qc_passed_at) {
            throw ValidationException::withMessages(["status" => __("app.qc_required_error")]);
        }

        return $this->move($order, $to, $user, $employeeId);
    }

    /** Quality check passed: the order is ready. */
    public function passQc(Order $order, User $user, ?int $employeeId = null, ?string $note = null): array
    {
        if ($order->statusRank() < $order->statusRank("ironing") || $order->statusRank() >= $order->statusRank("ready")) {
            throw ValidationException::withMessages(["status" => __("app.qc_wrong_stage")]);
        }

        $order->update(["qc_passed_at" => now(), "qc_by" => $user->id]);

        return $this->move($order, "ready", $user, $employeeId, $note ?: __("app.qc_passed"));
    }

    /**
     * Back to washing: failed quality check, manual decision or a complaint before
     * delivery. The stages are consumed again (the work is redone) and the pieces go
     * back with the order.
     */
    public function rework(Order $order, User $user, string $reason, string $source = "manual", ?Complaint $complaint = null): OrderRework
    {
        if ($order->status === "completed" || $order->abandoned_at || $order->statusRank() < $order->statusRank("washing")) {
            throw ValidationException::withMessages(["status" => __("app.rework_not_allowed")]);
        }

        return DB::transaction(function () use ($order, $user, $reason, $source, $complaint) {
            $from = $order->status;

            $rework = OrderRework::create([
                "order_id" => $order->id,
                "from_status" => $from,
                "reason" => $reason,
                "source" => $source,
                "complaint_id" => $complaint?->id,
                "user_id" => $user->id,
                "created_at" => now(),
            ]);

            // Redone stages consume their recipes again.
            $kept = array_values(array_filter($order->consumed_stages ?? [], fn ($s) => $s === "received"));

            Order::$stageNote = "rework";
            try {
                $order->update([
                    "status" => "washing",
                    "qc_passed_at" => null,
                    "qc_by" => null,
                    "ready_at" => null,
                    "consumed_stages" => $kept,
                    "rework_count" => $order->rework_count + 1,
                ]);
            } finally {
                Order::$stageNote = null;
            }

            $rank = $order->statusRank("washing");
            foreach ($order->pieces()->where("status", "!=", "completed")->get() as $piece) {
                if ($piece->statusRank() > $rank) {
                    $previous = $piece->status;
                    $piece->update(["status" => "washing", "status_at" => now()]);
                    OrderPieceEvent::create([
                        "order_piece_id" => $piece->id,
                        "from_status" => $previous,
                        "to_status" => "washing",
                        "user_id" => $user->id,
                        "note" => __("app.rework") . ": " . $reason,
                    ]);
                }
            }

            $consumption = app(OrderConsumption::class);
            $fresh = $order->fresh();
            if ($pending = $consumption->pendingStages($fresh)) {
                $consumption->consume($fresh, $user, $pending);
            }

            return $rework;
        });
    }

    /** A late order gets a new promise date; the first promise is kept for the on-time figures. */
    public function reschedule(Order $order, string $date, string $reason, bool $notify = true): void
    {
        $order->update([
            "original_delivery_date" => $order->original_delivery_date ?? $order->delivery_date,
            "delivery_date" => $date,
            "delay_reason" => $reason,
        ]);

        if ($notify) {
            $this->notifications->orderDelayed($order->fresh("customer"));
        }
    }

    /**
     * A complaint about delivered clothes: the customer brings them back and they are
     * washed again free of charge, as a new order linked to the original one.
     */
    public function reworkOrderFromComplaint(Complaint $complaint, User $user): Order
    {
        $original = $complaint->order()->with("items")->firstOrFail();

        $existing = Order::where("rework_of_order_id", $original->id)->whereIn("status", Order::PRODUCTION_STAGES)->first();
        if ($existing) {
            throw ValidationException::withMessages(["order" => __("app.rework_order_exists", ["order" => $existing->order_number])]);
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($original, $complaint, $user) {
                    $order = Order::create([
                        "order_number" => Order::nextOrderNumber(),
                        "customer_id" => $original->customer_id,
                        "received_at" => today()->toDateString(),
                        "delivery_date" => today()->addDays(1)->toDateString(),
                        "status" => "received",
                        "subtotal" => 0,
                        "vat_rate" => $original->vat_rate,
                        "vat_amount" => 0,
                        "total_price" => 0,
                        "care_flags" => $original->care_flags,
                        "notes" => __("app.rework_order_note", ["order" => $original->order_number, "complaint" => $complaint->number]),
                        "rework_of_order_id" => $original->id,
                    ]);

                    foreach ($original->items as $item) {
                        OrderItem::create([
                            "order_id" => $order->id,
                            "service_id" => $item->service_id,
                            "package_id" => $item->package_id,
                            "type" => $item->type,
                            "weight" => $item->weight,
                            "amount" => $item->amount,
                            "unit_price" => 0,
                            "list_price" => $item->unit_price,
                            "subtotal" => 0,
                        ]);
                    }

                    OrderRework::create([
                        "order_id" => $original->id,
                        "from_status" => $original->status,
                        "reason" => $complaint->description ?: __("app.complaint_category_" . $complaint->category),
                        "source" => "complaint",
                        "complaint_id" => $complaint->id,
                        "user_id" => $user->id,
                        "created_at" => now(),
                    ]);
                    $original->increment("rework_count");

                    return $order;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 5) {
                    throw $e;
                }
            }
        }
    }

    /** @return string[] warnings */
    private function move(Order $order, string $to, User $user, ?int $employeeId, ?string $note = null): array
    {
        Order::$stageEmployeeId = $employeeId;
        Order::$stageNote = $note;

        try {
            $result = $this->updateOrder->handle($order, [
                "customer_id" => $order->customer_id,
                "received_at" => $order->received_at->toDateString(),
                "status" => $to,
                "completed_at" => $order->completed_at,
            ], $user, []);
        } finally {
            Order::$stageEmployeeId = null;
            Order::$stageNote = null;
        }

        if ($result["error"]) {
            throw ValidationException::withMessages(["status" => $result["error"]]);
        }

        // Pieces follow the order on the board (they never move backwards).
        $rank = array_search($to, Order::STATUSES, true);
        foreach ($order->pieces()->get() as $piece) {
            if ($piece->statusRank() < $rank) {
                $previous = $piece->status;
                $piece->update(["status" => $to, "status_at" => now()]);
                OrderPieceEvent::create(["order_piece_id" => $piece->id, "from_status" => $previous, "to_status" => $to, "user_id" => $user->id, "note" => __("app.production_board")]);
            }
        }

        return $result["warnings"];
    }
}
