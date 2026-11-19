<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Key figures of each store for a period, and the company total. Each store is measured
 * inside CurrentStore::run(), so the figures come from the same scoped queries as the
 * store's own pages: a store's numbers can never include another store's data.
 */
class StoreOverview
{
    public const METRICS = ["orders", "sales", "received", "refunds", "expenses", "result", "receivables", "late_orders", "customers", "new_customers", "staff"];

    /**
     * @return array{stores: Collection<int, array>, totals: array<string, float|int>}
     */
    public function build(CarbonInterface $from, CarbonInterface $to, ?Collection $stores = null): array
    {
        $stores ??= Store::orderBy("name")->get();

        $rows = $stores->map(fn (Store $store) => ["store" => $store] + CurrentStore::run($store, fn () => $this->figures($store, $from, $to)));

        $totals = [];
        foreach (self::METRICS as $metric) {
            $totals[$metric] = $rows->sum($metric);
        }

        return ["stores" => $rows->values(), "totals" => $totals];
    }

    /** @return array<string, float|int> */
    public function figures(Store $store, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();
        $days = [$start->toDateString(), $end->toDateString()];

        $received = Money::round(Payment::whereBetween("paid_at", [$start, $end])->sum("amount"));
        $refunds = Money::round(CreditNote::valid()->refunds()->whereBetween("issued_at", [$start, $end])->sum("amount"));
        $expenses = Money::round(Expense::whereBetween("expense_date", $days)->sum("amount"));

        return [
            "orders" => Order::whereBetween("received_at", $days)->count(),
            "sales" => Money::round(Order::whereBetween("received_at", $days)->sum("total_price")),
            "received" => $received,
            "refunds" => $refunds,
            "expenses" => $expenses,
            "result" => $received - $refunds - $expenses,
            "receivables" => (float) Order::outstandingCredit()->with(["payments", "creditNotes"])->get()
                ->sum(fn (Order $order) => max(0, $order->remainingBalance())),
            "late_orders" => Order::late()->count(),
            "customers" => Customer::count(),
            "new_customers" => Customer::whereBetween("created_at", [$start, $end])->count(),
            "staff" => User::inStore($store->id)->where("is_active", true)->count(),
        ];
    }
}
