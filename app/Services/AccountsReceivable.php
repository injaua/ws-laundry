<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Collection;

class AccountsReceivable
{
    public const BUCKETS = ["current", "d1_30", "d31_60", "d61_90", "d90_plus"];

    public function outstandingOrders(): Collection
    {
        return Order::outstandingCredit()->with("customer")->orderBy("due_date")->get();
    }

    public function bucketOf(Order $order): string
    {
        $days = $order->daysOverdue();

        return match (true) {
            $days <= 0 => "current",
            $days <= 30 => "d1_30",
            $days <= 60 => "d31_60",
            $days <= 90 => "d61_90",
            default => "d90_plus",
        };
    }

    /**
     * Totals per aging bucket plus headline summary figures.
     */
    public function summary(Collection $orders): array
    {
        $byBucket = [];
        foreach (self::BUCKETS as $bucket) {
            $byBucket[$bucket] = 0.0;
        }

        foreach ($orders as $order) {
            $byBucket[$this->bucketOf($order)] += $order->remainingBalance();
        }

        $overdue = $orders->filter(fn (Order $order) => $order->isOverdue());

        return [
            "count" => $orders->count(),
            "total" => (float) $orders->sum(fn (Order $order) => $order->remainingBalance()),
            "overdue_count" => $overdue->count(),
            "overdue" => (float) $overdue->sum(fn (Order $order) => $order->remainingBalance()),
            "due_next_week" => (float) $orders
                ->filter(fn (Order $order) => $order->due_date
                    && $order->due_date->between(now()->startOfDay(), now()->addDays(7)->endOfDay())
                    && $order->remainingBalance() > 0)
                ->sum(fn (Order $order) => $order->remainingBalance()),
            "buckets" => $byBucket,
        ];
    }

    /**
     * One row per customer with outstanding amounts split by aging bucket.
     */
    public function agingByCustomer(Collection $orders): Collection
    {
        return $orders->groupBy("customer_id")->map(function ($customerOrders) {
            $first = $customerOrders->first();
            $summary = $this->summary($customerOrders);

            return [
                "customer" => $first->customer,
                "credit_limit" => (float) ($first->customer?->credit_limit ?? 0),
                "count" => $summary["count"],
                "total" => $summary["total"],
                "overdue" => $summary["overdue"],
                "buckets" => $summary["buckets"],
            ];
        })->sortByDesc("total")->values();
    }
}
