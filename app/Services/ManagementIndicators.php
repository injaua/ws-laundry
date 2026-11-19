<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\SiteSetting;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Management indicators (KPIs): finance, sales, operations, people and costs for a
 * period, compared with the previous period of the same length and with the
 * targets set by management.
 */
class ManagementIndicators
{
    /**
     * Indicator catalogue: group, format and which direction is good.
     * "snapshot" indicators describe the situation now (no period comparison).
     */
    public const INDICATORS = [
        // Finance
        "revenue" => ["group" => "finance", "format" => "money", "better" => "higher"],
        "collections" => ["group" => "finance", "format" => "money", "better" => "higher"],
        "expenses" => ["group" => "finance", "format" => "money", "better" => "lower"],
        "operating_result" => ["group" => "finance", "format" => "money", "better" => "higher"],
        "operating_margin" => ["group" => "finance", "format" => "percent", "better" => "higher"],
        "avg_ticket" => ["group" => "finance", "format" => "money", "better" => "higher"],
        "receivables" => ["group" => "finance", "format" => "money", "better" => "lower", "snapshot" => true],
        "overdue_share" => ["group" => "finance", "format" => "percent", "better" => "lower", "snapshot" => true],
        "dso" => ["group" => "finance", "format" => "days", "better" => "lower"],
        // Sales & customers
        "orders" => ["group" => "sales", "format" => "number", "better" => "higher"],
        "active_customers" => ["group" => "sales", "format" => "number", "better" => "higher"],
        "new_customers" => ["group" => "sales", "format" => "number", "better" => "higher"],
        "returning_rate" => ["group" => "sales", "format" => "percent", "better" => "higher"],
        "orders_per_customer" => ["group" => "sales", "format" => "decimal", "better" => "higher"],
        "discount_rate" => ["group" => "sales", "format" => "percent", "better" => "lower"],
        "express_share" => ["group" => "sales", "format" => "percent", "better" => "higher"],
        "delivery_revenue" => ["group" => "sales", "format" => "money", "better" => "higher"],
        // Operations
        "kg_processed" => ["group" => "operations", "format" => "kg", "better" => "higher"],
        "on_time_rate" => ["group" => "operations", "format" => "percent", "better" => "higher"],
        "cycle_hours" => ["group" => "operations", "format" => "hours", "better" => "lower"],
        "rework_rate" => ["group" => "operations", "format" => "percent", "better" => "lower"],
        "complaints_per_100" => ["group" => "operations", "format" => "decimal", "better" => "lower"],
        "late_now" => ["group" => "operations", "format" => "number", "better" => "lower", "snapshot" => true],
        // People
        "headcount" => ["group" => "people", "format" => "number", "better" => "higher", "snapshot" => true],
        "revenue_per_employee" => ["group" => "people", "format" => "money", "better" => "higher"],
        "kg_per_presence_day" => ["group" => "people", "format" => "kg", "better" => "higher"],
        "absenteeism" => ["group" => "people", "format" => "percent", "better" => "lower"],
        "payroll_ratio" => ["group" => "people", "format" => "percent", "better" => "lower"],
        // Costs & stock
        "material_cost_ratio" => ["group" => "costs", "format" => "percent", "better" => "lower"],
        "material_cost_per_kg" => ["group" => "costs", "format" => "money", "better" => "lower"],
        "stock_value" => ["group" => "costs", "format" => "money", "better" => "lower", "snapshot" => true],
        "low_stock_items" => ["group" => "costs", "format" => "number", "better" => "lower", "snapshot" => true],
    ];

    public const GROUPS = ["finance", "sales", "operations", "people", "costs"];

    public function __construct(private ProductionReport $production) {}

    /** @return array<string, float> */
    public static function targets(): array
    {
        $raw = json_decode((string) SiteSetting::get("kpi_targets", "{}"), true);

        return collect(is_array($raw) ? $raw : [])
            ->only(array_keys(self::INDICATORS))
            ->filter(fn ($v) => is_numeric($v))
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    public static function saveTargets(array $targets): void
    {
        $clean = collect($targets)->only(array_keys(self::INDICATORS))
            ->filter(fn ($v) => $v !== null && $v !== "" && is_numeric($v))
            ->map(fn ($v) => (float) $v)
            ->all();

        SiteSetting::set("kpi_targets", json_encode($clean));
    }

    /** The period before, with the same number of days. */
    public static function previousPeriod(CarbonInterface $from, CarbonInterface $to): array
    {
        $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        return [$from->copy()->subDays($days)->startOfDay(), $from->copy()->subDay()->endOfDay()];
    }

    /**
     * @return array{period: array, indicators: array, breakdowns: array}
     */
    public function build(CarbonInterface $from, CarbonInterface $to, bool $compare = true): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        $current = $this->compute($from, $to);
        $previous = null;
        if ($compare) {
            [$pFrom, $pTo] = self::previousPeriod($from, $to);
            $previous = $this->compute($pFrom, $pTo);
        }

        $targets = self::targets();
        $indicators = [];
        foreach (self::INDICATORS as $key => $meta) {
            $value = $current["values"][$key] ?? null;
            $prev = (!($meta["snapshot"] ?? false) && $previous) ? ($previous["values"][$key] ?? null) : null;

            $indicators[$key] = $meta + [
                "key" => $key,
                "value" => $value,
                "previous" => $prev,
                "change" => $this->change($value, $prev),
                "trend" => $this->trend($value, $prev, $meta["better"]),
                "target" => $targets[$key] ?? null,
                "status" => $this->status($value, $targets[$key] ?? null, $meta["better"]),
            ];
        }

        return [
            "period" => ["from" => $from, "to" => $to, "days" => (int) $from->diffInDays($to->copy()->startOfDay()) + 1],
            "indicators" => $indicators,
            "breakdowns" => $current["breakdowns"],
        ];
    }

    /** Monthly series for the trend charts (last N months, current one included). */
    public function monthlyTrend(int $months = 12): array
    {
        $start = today()->startOfMonth()->subMonths($months - 1);

        $orders = Order::whereDate("received_at", ">=", $start->toDateString())->get(["id", "customer_id", "received_at", "subtotal"]);
        $expenses = Expense::whereDate("expense_date", ">=", $start->toDateString())->get(["amount", "expense_date"]);
        $creditNotes = CreditNote::valid()->where("reverses_revenue", true)->whereDate("issued_at", ">=", $start->toDateString())->get(["net_amount", "issued_at"]);
        $ready = Order::where("ready_at", ">=", $start)->get(["ready_at", "delivery_date", "original_delivery_date"]);

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format("Y-m");
            $inMonth = fn ($date) => $date && Carbon::parse($date)->format("Y-m") === $key;

            $monthOrders = $orders->filter(fn ($o) => $inMonth($o->received_at));
            $revenue = (float) $monthOrders->sum("subtotal") - (float) $creditNotes->filter(fn ($c) => $inMonth($c->issued_at))->sum("net_amount");
            $spent = (float) $expenses->filter(fn ($e) => $inMonth($e->expense_date))->sum("amount");
            $monthReady = $ready->filter(fn ($o) => $inMonth($o->ready_at) && ($o->original_delivery_date ?? $o->delivery_date));
            $onTime = $monthReady->filter(fn ($o) => $o->ready_at->toDateString() <= ($o->original_delivery_date ?? $o->delivery_date)->toDateString())->count();

            $series[] = [
                "month" => $key,
                "label" => $month->translatedFormat("M y"),
                "revenue" => Money::round($revenue),
                "expenses" => Money::round($spent),
                "result" => Money::round($revenue - $spent),
                "orders" => $monthOrders->count(),
                "customers" => $monthOrders->pluck("customer_id")->unique()->count(),
                "on_time" => $monthReady->count() ? round($onTime / $monthReady->count() * 100, 1) : null,
            ];
        }

        return $series;
    }

    private function compute(Carbon $from, Carbon $to): array
    {
        $days = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // ---- Sales ----
        $orders = Order::with("items")->whereDate("received_at", ">=", $fromDate)->whereDate("received_at", "<=", $toDate)->get();
        $billed = $orders->whereNull("rework_of_order_id");
        $creditNotes = (float) CreditNote::valid()->where("reverses_revenue", true)
            ->whereDate("issued_at", ">=", $fromDate)->whereDate("issued_at", "<=", $toDate)->sum("net_amount");
        $revenue = (float) $billed->sum("subtotal") - $creditNotes;

        $collections = (float) Payment::whereDate("paid_at", ">=", $fromDate)->whereDate("paid_at", "<=", $toDate)->sum("amount")
            - (float) CreditNote::valid()->refunds()->whereDate("issued_at", ">=", $fromDate)->whereDate("issued_at", "<=", $toDate)->sum("amount");

        $expenseRows = Expense::whereDate("expense_date", ">=", $fromDate)->whereDate("expense_date", "<=", $toDate)->get(["category", "amount"]);
        $expenses = (float) $expenseRows->sum("amount");
        $salaryKeys = ExpenseCategory::where("is_salary", true)->pluck("key")->all();
        $payroll = (float) $expenseRows->whereIn("category", $salaryKeys)->sum("amount");

        $result = $revenue - $expenses;
        $customerIds = $billed->pluck("customer_id")->unique();

        [$pFrom, $pTo] = self::previousPeriod($from, $to);
        $previousCustomers = Order::whereNull("rework_of_order_id")
            ->whereDate("received_at", ">=", $pFrom->toDateString())->whereDate("received_at", "<=", $pTo->toDateString())
            ->distinct()->pluck("customer_id");
        $returning = $previousCustomers->intersect($customerIds)->count();

        // Promotions / customer prices: what the list price would have charged.
        $gross = 0.0;
        $discount = 0.0;
        foreach ($billed as $order) {
            foreach ($order->items as $item) {
                $qty = $item->type === "by_weight" ? (float) $item->weight : (float) $item->amount;
                $list = $item->list_price !== null ? (float) $item->list_price : (float) $item->unit_price;
                $gross += $list * $qty;
                $discount += max(0, ($list - (float) $item->unit_price) * $qty);
            }
        }

        // ---- Receivables (now) ----
        $ar = app(AccountsReceivable::class);
        $receivables = $ar->summary($ar->outstandingOrders());

        // ---- Operations ----
        $prod = $this->production->build($from, $to);
        $readyOrders = Order::whereBetween("ready_at", [$from, $to])->get(["created_at", "ready_at"]);
        $cycleHours = $readyOrders->isNotEmpty()
            ? round($readyOrders->avg(fn ($o) => $o->created_at->diffInMinutes($o->ready_at) / 60), 1)
            : null;

        // ---- People ----
        $headcount = Employee::whereIn("status", ["probation", "active"])->count();
        $attendance = Attendance::whereDate("attendance_date", ">=", $fromDate)->whereDate("attendance_date", "<=", $toDate)->get(["status"]);
        $present = $attendance->whereIn("status", ["present", "late"])->count();
        $absent = $attendance->where("status", "absent")->count();
        $scheduled = $attendance->whereIn("status", ["present", "late", "absent"])->count();

        // ---- Costs & stock ----
        $materialCost = (float) $prod["materials"]["total_cost"];
        $stockItems = InventoryItem::where("is_active", true)->get(["stock_qty", "unit_cost", "min_stock_qty"]);

        $ratio = fn ($a, $b) => $b > 0 ? round($a / $b * 100, 1) : null;

        $values = [
            "revenue" => Money::round($revenue),
            "collections" => Money::round($collections),
            "expenses" => Money::round($expenses),
            "operating_result" => Money::round($result),
            "operating_margin" => $ratio($result, $revenue),
            "avg_ticket" => $billed->count() ? Money::round($revenue / $billed->count()) : null,
            "receivables" => Money::round($receivables["total"]),
            "overdue_share" => $ratio($receivables["overdue"], $receivables["total"]),
            "dso" => $revenue > 0 ? round($receivables["total"] / ($revenue / $days), 1) : null,
            "orders" => $billed->count(),
            "active_customers" => $customerIds->count(),
            "new_customers" => Customer::whereBetween("created_at", [$from, $to])->count(),
            "returning_rate" => $ratio($returning, $previousCustomers->count()),
            "orders_per_customer" => $customerIds->count() ? round($billed->count() / $customerIds->count(), 2) : null,
            "discount_rate" => $ratio($discount, $gross),
            "express_share" => $ratio($billed->where("is_express", true)->count(), $billed->count()),
            "delivery_revenue" => Money::sum($billed->pluck("delivery_fee")),
            "kg_processed" => $prod["output"]["kg"],
            "on_time_rate" => $prod["punctuality"]["rate"],
            "cycle_hours" => $cycleHours,
            "rework_rate" => $prod["quality"]["rework_rate"],
            "complaints_per_100" => $prod["quality"]["complaints_per_100"],
            "late_now" => $prod["output"]["late_now"],
            "headcount" => $headcount,
            "revenue_per_employee" => $headcount ? Money::round($revenue / $headcount) : null,
            "kg_per_presence_day" => $present ? round($prod["output"]["kg"] / $present, 1) : null,
            "absenteeism" => $ratio($absent, $scheduled),
            "payroll_ratio" => $ratio($payroll, $revenue),
            "material_cost_ratio" => $ratio($materialCost, $revenue),
            "material_cost_per_kg" => $prod["materials"]["cost_per_kg"],
            "stock_value" => round($stockItems->sum(fn ($i) => max(0, (float) $i->stock_qty) * (float) $i->unit_cost)),
            "low_stock_items" => $stockItems->filter(fn ($i) => (float) $i->min_stock_qty > 0 && (float) $i->stock_qty <= (float) $i->min_stock_qty)->count(),
        ];

        return ["values" => $values, "breakdowns" => $this->breakdowns($billed, $expenseRows, $revenue)];
    }

    private function breakdowns(Collection $orders, Collection $expenses, float $revenue): array
    {
        // Revenue by line type (fees apart).
        $byType = ["by_weight" => 0.0, "by_piece" => 0.0, "by_package" => 0.0];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $byType[$item->type] = ($byType[$item->type] ?? 0) + (float) $item->subtotal;
            }
        }
        $byType["express_fee"] = (float) $orders->sum("express_fee");
        $byType["delivery_fee"] = (float) $orders->sum("delivery_fee");

        $topCustomers = $orders->groupBy("customer_id")
            ->map(fn ($group) => ["customer_id" => $group->first()->customer_id, "orders" => $group->count(), "revenue" => (float) $group->sum("subtotal")])
            ->sortByDesc("revenue")->take(5)->values();
        $names = Customer::whereIn("id", $topCustomers->pluck("customer_id"))->pluck("name", "id");
        $topCustomers = $topCustomers->map(fn ($row) => $row + [
            "name" => $names[$row["customer_id"]] ?? "—",
            "share" => $revenue > 0 ? round($row["revenue"] / $revenue * 100, 1) : null,
        ]);

        $categoryNames = ExpenseCategory::pluck("name", "key");
        $byCategory = $expenses->groupBy("category")
            ->map(fn ($group, $key) => ["name" => $categoryNames[$key] ?? $key, "amount" => (float) $group->sum("amount")])
            ->sortByDesc("amount")->values();

        return [
            "revenue_by_type" => array_filter($byType, fn ($v) => $v > 0),
            "top_customers" => $topCustomers,
            "expenses_by_category" => $byCategory,
        ];
    }

    private function change(?float $value, ?float $previous): ?float
    {
        if ($value === null || $previous === null || (float) $previous === 0.0) {
            return null;
        }

        return round(($value - $previous) / abs($previous) * 100, 1);
    }

    /** up/down/flat, and whether that is good. */
    private function trend(?float $value, ?float $previous, string $better): ?array
    {
        if ($value === null || $previous === null) {
            return null;
        }

        $direction = $value <=> $previous;
        if ($direction === 0) {
            return ["direction" => "flat", "good" => null];
        }

        return [
            "direction" => $direction > 0 ? "up" : "down",
            "good" => ($direction > 0) === ($better === "higher"),
        ];
    }

    /** Traffic light against the target: green on target, amber within 10 %, red beyond. */
    private function status(?float $value, ?float $target, string $better): ?string
    {
        if ($value === null || $target === null) {
            return null;
        }

        if ($better === "higher") {
            return $value >= $target ? "good" : ($value >= $target * 0.9 ? "warning" : "bad");
        }

        return $value <= $target ? "good" : ($value <= $target * 1.1 ? "warning" : "bad");
    }
}
