<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use Carbon\CarbonInterface;

/**
 * Daily capacity and realistic promise dates.
 *
 * Capacity is set per day: kilos the laundry washes and pieces it irons. The work
 * already in production (not ready yet) is the queue. A new order can be ready
 * once the queue plus the new order fits in the following days, and never before
 * its service lead time. Express orders jump the queue: only other express orders
 * count ahead of them.
 */
class ProductionPlanner
{
    /** @return array{kg: ?float, pieces: ?int} null = not limited */
    public static function capacity(): array
    {
        $kg = (float) SiteSetting::get("production_capacity_kg", 0);
        $pieces = (int) SiteSetting::get("production_capacity_pieces", 0);

        return ["kg" => $kg > 0 ? $kg : null, "pieces" => $pieces > 0 ? $pieces : null];
    }

    public static function enabled(): bool
    {
        $capacity = self::capacity();

        return $capacity["kg"] !== null || $capacity["pieces"] !== null;
    }

    /** @return array{kg: float, pieces: int, orders: int} */
    public function queue(bool $expressOnly = false, ?int $exceptOrderId = null): array
    {
        $orders = Order::inProduction()
            ->when($expressOnly, fn ($q) => $q->where("is_express", true))
            ->when($exceptOrderId, fn ($q) => $q->where("id", "!=", $exceptOrderId))
            ->with("items")
            ->get();

        return [
            "kg" => round($orders->sum(fn (Order $o) => $o->weightKg()), 2),
            "pieces" => (int) $orders->sum(fn (Order $o) => $o->pieceCount()),
            "orders" => $orders->count(),
        ];
    }

    /**
     * Earliest realistic ready date for a new order.
     *
     * @return array{date: string, capacity_date: ?string, days_needed: ?int, queue_kg: float, queue_pieces: int, fits: bool, enabled: bool}
     */
    public function promise(CarbonInterface $receivedAt, float $kg, int $pieces, int $leadDays, bool $express = false, ?string $wantedDate = null): array
    {
        $serviceDate = $receivedAt->copy()->startOfDay()->addDays(max(0, $leadDays));
        $capacity = self::capacity();
        $queue = $this->queue($express);

        if (!self::enabled()) {
            return [
                "date" => ($wantedDate ?? $serviceDate->toDateString()),
                "capacity_date" => null, "days_needed" => null,
                "queue_kg" => $queue["kg"], "queue_pieces" => $queue["pieces"],
                "fits" => true, "enabled" => false,
            ];
        }

        // Days of work ahead, including the new order; today counts as the first day.
        $days = 1;
        if ($capacity["kg"] !== null) {
            $days = max($days, (int) ceil(($queue["kg"] + $kg) / $capacity["kg"]));
        }
        if ($capacity["pieces"] !== null) {
            $days = max($days, (int) ceil(($queue["pieces"] + $pieces) / $capacity["pieces"]));
        }

        $start = $receivedAt->copy()->startOfDay()->max(today());
        $capacityDate = $start->copy()->addDays($days);
        $date = $serviceDate->max($capacityDate);

        // Asked for a date (e.g. express promise): does the queue allow it?
        $fits = $wantedDate === null || now()->parse($wantedDate)->startOfDay()->gte($capacityDate);

        return [
            "date" => $wantedDate ?? $date->toDateString(),
            "capacity_date" => $capacityDate->toDateString(),
            "days_needed" => $days,
            "queue_kg" => $queue["kg"],
            "queue_pieces" => $queue["pieces"],
            "fits" => $fits,
            "enabled" => true,
        ];
    }
}
