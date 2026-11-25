<?php

namespace App\Services\Ai;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Support\CurrentStore;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Yesterday's figures and the forecast for tomorrow, the next 7 days and the month, for the
 * store worked in (or every store: company()).
 *
 * The forecast is arithmetic, not AI: the average of the same weekday over the last 8 weeks,
 * adjusted by the trend of the last 4 weeks against the 4 before (limited to ±20%). Orders
 * already promised for tomorrow are counted from the real data. The AI only comments on it.
 */
class Forecast
{
    private const WEEKS = 8;

    /** @return array<string, mixed> */
    public function store(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $yesterday = $today->subDay();
        $start = $today->subWeeks(self::WEEKS);

        $orders = $this->daily(Order::query(), "received_at", $start, $yesterday, "total_price");
        $received = $this->daily(Payment::whereNull("bounced_at"), "paid_at", $start, $yesterday, "amount");

        $factor = $this->trend($received["sum"], $yesterday);
        $predict = function (CarbonImmutable $day) use ($orders, $received, $factor) {
            $dow = $day->dayOfWeekIso;

            return [
                "orders" => round(($orders["count_by_dow"][$dow] ?? 0) * $factor, 1),
                "sales" => Money::round(($orders["sum_by_dow"][$dow] ?? 0) * $factor),
                "received" => Money::round(($received["sum_by_dow"][$dow] ?? 0) * $factor),
            ];
        };
        $sumDays = function (CarbonImmutable $from, CarbonImmutable $to) use ($predict) {
            $total = ["orders" => 0.0, "sales" => 0.0, "received" => 0.0];
            for ($d = $from; $d->lte($to); $d = $d->addDay()) {
                foreach ($predict($d) as $k => $v) {
                    $total[$k] += $v;
                }
            }

            return ["orders" => round($total["orders"]), "sales" => Money::round($total["sales"]), "received" => Money::round($total["received"])];
        };

        $tomorrow = $today->addDay();
        $monthStart = $today->startOfMonth();
        $monthToDate = [
            "orders" => Order::whereBetween("received_at", [$monthStart->toDateString(), $today->toDateString()])->count(),
            "sales" => Money::round(Order::whereBetween("received_at", [$monthStart->toDateString(), $today->toDateString()])->sum("total_price")),
            "received" => Money::round(Payment::whereNull("bounced_at")->whereBetween("paid_at", [$monthStart->startOfDay(), $today->endOfDay()])->sum("amount")),
        ];
        $rest = $tomorrow->lte($today->endOfMonth()) ? $sumDays($tomorrow, $today->endOfMonth()->startOfDay()) : ["orders" => 0, "sales" => 0.0, "received" => 0.0];
        $lastMonth = [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()];

        return [
            "yesterday" => [
                "date" => $yesterday->toDateString(),
                "orders" => (int) ($orders["count"][$yesterday->toDateString()] ?? 0),
                "sales" => Money::round($orders["sum"][$yesterday->toDateString()] ?? 0),
                "received" => Money::round($received["sum"][$yesterday->toDateString()] ?? 0),
                "same_weekday_average_received" => Money::round($received["sum_by_dow"][$yesterday->dayOfWeekIso] ?? 0),
                "completed" => Order::whereDate("completed_at", $yesterday)->count(),
            ],
            "tomorrow" => $predict($tomorrow) + [
                "date" => $tomorrow->toDateString(),
                "promised_orders" => Order::inProduction()->whereDate("delivery_date", $tomorrow)->count(),
                "late_now" => Order::late()->count(),
            ],
            "next_7_days" => $sumDays($tomorrow, $today->addDays(7)) + ["from" => $tomorrow->toDateString(), "to" => $today->addDays(7)->toDateString()],
            "month" => [
                "month" => $today->format("Y-m"),
                "to_date" => $monthToDate,
                "projected" => [
                    "orders" => $monthToDate["orders"] + $rest["orders"],
                    "sales" => Money::add($monthToDate["sales"], $rest["sales"]),
                    "received" => Money::add($monthToDate["received"], $rest["received"]),
                ],
                "last_month" => [
                    "orders" => Order::whereBetween("received_at", [$lastMonth[0]->toDateString(), $lastMonth[1]->toDateString()])->count(),
                    "sales" => Money::round(Order::whereBetween("received_at", [$lastMonth[0]->toDateString(), $lastMonth[1]->toDateString()])->sum("total_price")),
                    "received" => Money::round(Payment::whereNull("bounced_at")->whereBetween("paid_at", [$lastMonth[0], $lastMonth[1]])->sum("amount")),
                ],
            ],
            "trend_factor" => round($factor, 2),
            "method" => "Average of the same weekday over the last " . self::WEEKS . " weeks, adjusted by the trend of the last 4 weeks (max ±20%).",
        ];
    }

    /** Every active store, and the company totals. @return array{stores: array<int, array<string, mixed>>, totals: array<string, mixed>} */
    public function company(?CarbonImmutable $today = null): array
    {
        $stores = [];
        foreach (Store::active()->orderBy("name")->get() as $store) {
            $stores[] = ["store" => $store->name] + CurrentStore::run($store, fn () => $this->store($today));
        }

        $sum = fn (string $section, string $key, ?string $sub = null) => Money::round(array_sum(array_map(
            fn ($s) => (float) ($sub ? $s[$section][$sub][$key] : $s[$section][$key]),
            $stores,
        )));

        $totals = [];
        foreach (["yesterday", "tomorrow", "next_7_days"] as $section) {
            foreach (["orders", "sales", "received"] as $key) {
                $totals[$section][$key] = $sum($section, $key);
            }
        }
        foreach (["to_date", "projected", "last_month"] as $sub) {
            foreach (["orders", "sales", "received"] as $key) {
                $totals["month"][$sub][$key] = $sum("month", $key, $sub);
            }
        }
        $totals["tomorrow"]["promised_orders"] = (int) $sum("tomorrow", "promised_orders");
        $totals["tomorrow"]["late_now"] = (int) $sum("tomorrow", "late_now");

        return ["stores" => $stores, "totals" => $totals];
    }

    /**
     * Daily count / sum between two days, and their weekday averages over the window.
     *
     * @return array{count: array<string, int>, sum: array<string, float>, count_by_dow: array<int, float>, sum_by_dow: array<int, float>}
     */
    private function daily(Builder $query, string $column, CarbonImmutable $from, CarbonImmutable $to, string $sumColumn): array
    {
        $rows = $query->whereBetween($column, [$from->startOfDay(), $to->endOfDay()])
            ->selectRaw("DATE({$column}) as day, COUNT(*) as n, SUM({$sumColumn}) as total")
            ->groupBy(DB::raw("DATE({$column})"))
            ->toBase()
            ->get();

        $byDay = [];
        $sumByDay = [];
        foreach ($rows as $row) {
            $byDay[(string) $row->day] = (int) $row->n;
            $sumByDay[(string) $row->day] = (float) $row->total;
        }

        $countByDow = [];
        $sumByDow = [];
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $dow = $d->dayOfWeekIso;
            $countByDow[$dow][] = $byDay[$d->toDateString()] ?? 0;
            $sumByDow[$dow][] = $sumByDay[$d->toDateString()] ?? 0.0;
        }
        $avg = fn (array $values) => $values ? array_sum($values) / count($values) : 0.0;

        return [
            "count" => $byDay,
            "sum" => $sumByDay,
            "count_by_dow" => array_map($avg, $countByDow),
            "sum_by_dow" => array_map($avg, $sumByDow),
        ];
    }

    /** Last 4 weeks against the 4 before, limited to ±20%. */
    private function trend(array $sumByDay, CarbonImmutable $yesterday): float
    {
        $recent = 0.0;
        $before = 0.0;
        foreach ($sumByDay as $day => $total) {
            $age = CarbonImmutable::parse($day)->diffInDays($yesterday);
            if ($age < 28) {
                $recent += $total;
            } elseif ($age < 56) {
                $before += $total;
            }
        }
        if ($before <= 0) {
            return 1.0;
        }

        return max(0.8, min(1.2, $recent / $before));
    }
}
