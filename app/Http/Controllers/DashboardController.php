<?php

namespace App\Http\Controllers;

use App\Models\AiInsight;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\MaintenanceJob;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AccountsReceivable;
use App\Services\ManagementIndicators;
use App\Support\CurrentStore;
use App\Support\StoreReadiness;
use App\Support\SystemSettings;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /** Management indicators shown on the dashboard (all of them: Management indicators page). */
    private const KPI_CACHE_MINUTES = 10;

    private const DASHBOARD_KPIS = ["revenue", "operating_result", "operating_margin", "orders", "avg_ticket", "on_time_rate", "active_customers", "receivables"];

    public function index()
    {
        // Couriers work from their route; the dashboard figures are not theirs to see.
        if (auth()->user()->isCourier()) {
            return redirect()->route("courier.route");
        }

        // Managers and the accountant see the whole business; cashiers their own takings.
        $isManager = auth()->user()->seesFinance();

        $totalCustomers = Customer::count();
        $newCustomers = Customer::whereMonth("created_at", now()->month)->count();
        $todayOrders = Order::whereDate("received_at", today())->count();
        $processingOrders = Order::whereNotIn("status", ["completed", "delivering"])->count();
        $readyOrders = Order::where("status", "ready")->count();

        $todayRevenueQuery = Payment::whereDate("paid_at", today());
        if (!$isManager) {
            $todayRevenueQuery->where("user_id", auth()->id());
        }
        // Refunds handed back are not revenue.
        $todayRefunds = CreditNote::valid()->refunds()->whereDate("issued_at", today())
            ->when(!$isManager, fn ($q) => $q->where("user_id", auth()->id()));
        $todayRevenue = $todayRevenueQuery->sum("amount") - $todayRefunds->sum("amount");

        $monthRevenue = 0;
        $chartLabels = [];
        $chartData = [];

        if ($isManager) {
            $monthRevenue = Payment::whereMonth("paid_at", now()->month)
                ->whereYear("paid_at", now()->year)
                ->sum("amount")
                - CreditNote::valid()->refunds()->whereMonth("issued_at", now()->month)
                    ->whereYear("issued_at", now()->year)->sum("amount");

            // 7 days chart: takings less refunds per day, one grouped query each.
            $since = today()->subDays(6);
            $paid = Payment::where("paid_at", ">=", $since)
                ->selectRaw("DATE(paid_at) as day, SUM(amount) as total")->groupBy("day")->pluck("total", "day");
            $refunded = CreditNote::valid()->refunds()->where("issued_at", ">=", $since)
                ->selectRaw("DATE(issued_at) as day, SUM(amount) as total")->groupBy("day")->pluck("total", "day");
            for ($i = 6; $i >= 0; $i--) {
                $date = today()->subDays($i);
                $chartLabels[] = $date->format("d M");
                $chartData[] = (float) ($paid[$date->toDateString()] ?? 0) - (float) ($refunded[$date->toDateString()] ?? 0);
            }
        }

        $latestOrders = Order::with("customer")
            ->latest()
            ->take(5)
            ->get();

        $resourceAlerts = null;
        if (auth()->user()->isManager()) {
            $resourceAlerts = [
                "low_stock" => InventoryItem::where("is_active", true)
                    ->where("min_stock_qty", ">", 0)
                    ->whereColumn("stock_qty", "<=", "min_stock_qty")
                    ->count(),
                "faulty" => Equipment::whereIn("status", ["faulty", "in_maintenance"])->count(),
                "due_maintenance" => MaintenanceJob::where("kind", "preventive")
                    ->whereNotIn("status", ["completed", "cancelled"])
                    ->whereNotNull("next_service_date")
                    ->whereDate("next_service_date", "<=", now()->addDays(7)->toDateString())
                    ->count(),
                "expiring_lots" => InventoryLot::open()->expiringSoon()->count(),
                "expired_lots" => InventoryLot::open()->expired()->count(),
            ];
        }

        $receivablesAlerts = null;
        if ($isManager) {
            $ar = app(AccountsReceivable::class);
            $receivablesAlerts = $ar->summary($ar->outstandingOrders());
        }

        // Management (admin / owner): the key indicators of the store this month, against the period
        // before. Heavy to compute, so kept for a few minutes per store and day.
        $storeKpis = null;
        $kpiStore = null;
        $kpiComputedAt = null;
        if (auth()->user()->can("manage-store")) {
            $kpiStore = CurrentStore::store();
            [$storeKpis, $kpiComputedAt] = Cache::remember(
                "dashboard-kpis:" . ($kpiStore?->id ?? 0) . ":" . today()->toDateString(),
                now()->addMinutes(self::KPI_CACHE_MINUTES),
                fn () => [
                    collect(app(ManagementIndicators::class)->build(now()->startOfMonth(), now())["indicators"])
                        ->only(self::DASHBOARD_KPIS)->all(),
                    now()->format((string) SystemSettings::get("time_format") ?: "H:i"),
                ],
            );
        }

        // Is the store ready to take payments and talk to customers? (admin / owner)
        $storeReadiness = auth()->user()->can("configure-store") ? StoreReadiness::for(CurrentStore::id()) : null;

        // Latest open insights (admin / owner who receive them).
        $user = auth()->user();
        $aiInsights = $user->can("receive-insights") && $user->ai_insights_enabled
            ? AiInsight::visibleTo($user, CurrentStore::id())->open()->latest("period_date")
                ->orderByRaw("FIELD(severity, 'critical', 'warning', 'info')")->latest("id")->limit(4)->get()
            : collect();

        return view("dashboard", compact(
            "aiInsights", "storeKpis", "kpiStore", "kpiComputedAt", "storeReadiness",
            "isManager", "totalCustomers", "newCustomers", "todayOrders", "processingOrders",
            "readyOrders", "todayRevenue", "monthRevenue",
            "latestOrders", "chartLabels", "chartData", "resourceAlerts", "receivablesAlerts"
        ));
    }
}
