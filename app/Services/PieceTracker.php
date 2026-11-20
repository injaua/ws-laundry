<?php

namespace App\Services;

use App\Actions\Orders\UpdateOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPiece;
use App\Models\OrderPieceEvent;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Piece-by-piece tracking. Each garment has its own code and moves forward through
 * the same stages as the order (received → … → ready → completed = handed over).
 * The order follows its slowest piece: once every piece has reached a stage the
 * order advances to it, up to "ready". Completing the order stays a deliberate
 * step (payment, invoice), and marks every piece as handed over.
 */
class PieceTracker
{
    /** The order is never moved past this stage by scans. */
    private const AUTO_ORDER_LIMIT = "ready";

    /** Max pieces created in one go: protects against typos like 500 instead of 5. */
    public const MAX_BATCH = 200;

    /** One piece per unit of every "by piece" line, at order creation. */
    public function createForOrder(Order $order, ?User $user = null): Collection
    {
        $created = collect();

        foreach ($order->items()->with("service")->get() as $item) {
            if ($item->type === "by_piece") {
                $created = $created->merge($this->add($order, $item, (int) $item->amount, null, $user));
            }
        }

        return $created;
    }

    /** Add $count pieces to an order, numbered after the existing ones. */
    public function add(Order $order, ?OrderItem $item, int $count, ?string $description, ?User $user): Collection
    {
        $count = max(0, min($count, self::MAX_BATCH));
        $description = trim((string) $description) ?: ($item?->item_name ?? __("app.piece"));

        return DB::transaction(function () use ($order, $item, $count, $description, $user) {
            // Two people adding pieces to the same order wait for each other on the order row.
            // Locking the order (not "MAX(seq) … FOR UPDATE" on order_pieces) matters: for an
            // order without pieces that query locks the gap at the end of the index, which
            // every new order of every store shares, so concurrent orders deadlocked.
            Order::withoutGlobalScopes()->whereKey($order->id)->lockForUpdate()->value("id");
            $seq = (int) OrderPiece::where("order_id", $order->id)->max("seq");
            $status = $order->status === "completed" ? "completed" : "received";
            $pieces = collect();

            for ($i = 0; $i < $count; $i++) {
                $seq++;
                $piece = OrderPiece::create([
                    "order_id" => $order->id,
                    "order_item_id" => $item?->id,
                    "seq" => $seq,
                    "code" => self::codeFor($order, $seq),
                    "description" => mb_substr($description, 0, 120),
                    "status" => $status,
                    "status_at" => now(),
                ]);
                $this->log($piece, null, $status, $user, __("app.piece_event_created"));
                $pieces->push($piece);
            }

            return $pieces;
        });
    }

    public static function codeFor(Order $order, int $seq): string
    {
        return mb_strtoupper($order->order_number) . "-" . str_pad((string) $seq, 2, "0", STR_PAD_LEFT);
    }

    /**
     * A barcode read at a station. The code may be a piece (moves that piece) or an
     * order number (moves all its pieces that are behind the stage: handy for a
     * whole machine load).
     *
     * @return array{ok: bool, message: string, warnings: string[], pieces: Collection, order: ?Order}
     */
    public function scan(string $code, string $stage, User $user): array
    {
        $code = mb_strtoupper(trim($code));

        if ($piece = OrderPiece::with("order.customer")->where("code", $code)->first()) {
            return $this->moveMany($piece->order, collect([$piece]), $stage, $user);
        }

        $order = Order::with("customer")->where("order_number", $code)->first();
        if ($order) {
            $pieces = $order->pieces()->get();
            if ($pieces->isEmpty()) {
                return $this->result(false, __("app.scan_order_without_pieces", ["order" => $order->order_number]), order: $order);
            }

            return $this->moveMany($order, $pieces, $stage, $user, wholeOrder: true);
        }

        return $this->result(false, __("app.scan_unknown_code", ["code" => $code]));
    }

    /** Move one piece by hand (order page), with an optional note. */
    public function move(OrderPiece $piece, string $stage, User $user, ?string $note = null): array
    {
        return $this->moveMany($piece->order, collect([$piece]), $stage, $user, note: $note);
    }

    private function moveMany(Order $order, Collection $pieces, string $stage, User $user, bool $wholeOrder = false, ?string $note = null): array
    {
        if ($order->trashed()) {
            return $this->result(false, __("app.scan_order_deleted", ["order" => $order->order_number]), order: $order);
        }

        // Handing a piece over is part of delivering the order, which must still be open.
        if ($order->status === "completed" && $stage !== "completed") {
            return $this->result(false, __("app.scan_order_completed", ["order" => $order->order_number]), pieces: $pieces, order: $order);
        }

        $target = array_search($stage, Order::STATUSES, true);
        $moved = collect();
        $behind = collect();

        foreach ($pieces as $piece) {
            $rank = $piece->statusRank();
            if ($rank < $target) {
                $from = $piece->status;
                $piece->update(["status" => $stage, "status_at" => now()]);
                $this->log($piece, $from, $stage, $user, $note);
                $moved->push($piece);
            } elseif ($rank > $target) {
                $behind->push($piece);
            }
        }

        $label = __("app.status_" . $stage);
        $warnings = [];

        if ($moved->isEmpty()) {
            $single = $pieces->count() === 1 ? $pieces->first() : null;
            $message = $single && $single->statusRank() > $target
                ? __("app.scan_piece_ahead", ["code" => $single->code, "stage" => __("app.status_" . $single->status)])
                : __("app.scan_already_at", ["stage" => $label]);

            return $this->result($single === null || $single->statusRank() === $target, $message, [], $pieces, $order);
        }

        if ($wholeOrder && $behind->isNotEmpty()) {
            $warnings[] = __("app.scan_some_ahead", ["count" => $behind->count()]);
        }
        if ($issue = $moved->first(fn ($p) => filled($p->issue))) {
            $warnings[] = __("app.scan_piece_has_issue", ["code" => $issue->code, "issue" => $issue->issue]);
        }

        $warnings = array_merge($warnings, $this->syncOrder($order->fresh(), $user));

        $message = $moved->count() === 1
            ? __("app.scan_piece_moved", ["code" => $moved->first()->code, "stage" => $label])
            : __("app.scan_pieces_moved", ["count" => $moved->count(), "stage" => $label]);

        return $this->result(true, $message, $warnings, $moved, $order->fresh("customer"));
    }

    /**
     * Advance the order to the stage every piece has reached (never past "ready"), so
     * the existing stage effects run: stock consumption, the "ready" WhatsApp notice.
     *
     * @return string[] warnings
     */
    public function syncOrder(Order $order, User $user): array
    {
        $ranks = $order->pieces()->pluck("status")->map(fn ($s) => (int) array_search($s, Order::STATUSES, true));
        if ($ranks->isEmpty() || $order->status === "completed") {
            return [];
        }

        $target = min($ranks->min(), (int) array_search(self::AUTO_ORDER_LIMIT, Order::STATUSES, true));
        if ($target <= $order->statusRank()) {
            return [];
        }

        $status = Order::STATUSES[$target];
        $result = app(UpdateOrder::class)->handle($order, [
            "customer_id" => $order->customer_id,
            "received_at" => $order->received_at->toDateString(),
            "status" => $status,
        ], $user, []);

        $warnings = $result["warnings"];
        if ($result["error"] === null) {
            array_unshift($warnings, __("app.scan_order_advanced", ["order" => $order->order_number, "stage" => __("app.status_" . $status)]));
        }

        return $warnings;
    }

    /** The order was completed (handed over): every piece goes with it. */
    public function orderCompleted(Order $order, User $user): void
    {
        foreach ($order->pieces()->where("status", "!=", "completed")->get() as $piece) {
            $from = $piece->status;
            $piece->update(["status" => "completed", "status_at" => now()]);
            $this->log($piece, $from, "completed", $user, __("app.piece_event_with_order"));
        }
    }

    /** Pieces that are not ready yet: shown as a warning when the order is delivered. */
    public function notReady(Order $order): Collection
    {
        $ready = array_search("ready", Order::STATUSES, true);

        return $order->pieces()->get()->filter(fn ($p) => $p->statusRank() < $ready)->values();
    }

    /** @return array<string, int> pieces per stage, in stage order */
    public function stageCounts(Collection $pieces): array
    {
        $counts = array_fill_keys(Order::STATUSES, 0);
        foreach ($pieces as $piece) {
            $counts[$piece->status] = ($counts[$piece->status] ?? 0) + 1;
        }

        return array_filter($counts);
    }

    private function log(OrderPiece $piece, ?string $from, string $to, ?User $user, ?string $note): void
    {
        OrderPieceEvent::create([
            "order_piece_id" => $piece->id,
            "user_id" => $user?->id,
            "from_status" => $from,
            "to_status" => $to,
            "note" => $note ? mb_substr($note, 0, 255) : null,
        ]);
    }

    private function result(bool $ok, string $message, array $warnings = [], ?Collection $pieces = null, ?Order $order = null): array
    {
        return ["ok" => $ok, "message" => $message, "warnings" => $warnings, "pieces" => $pieces ?? collect(), "order" => $order];
    }
}
