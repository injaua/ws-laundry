<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $isManager = auth()->user()->isAdmin() || auth()->user()->isOwner();

        $totalCustomers = Customer::count();
        $newCustomers = Customer::whereMonth("created_at", now()->month)->count();
        $todayOrders = Order::whereDate("received_at", today())->count();
        $processingOrders = Order::whereNotIn("status", ["completed", "delivering"])->count();
        $readyOrders = Order::where("status", "ready")->count();

        $todayRevenueQuery = Payment::whereDate("paid_at", today());
        if (! $isManager) {
            $todayRevenueQuery->where("user_id", auth()->id());
        }
        $todayRevenue = $todayRevenueQuery->sum("amount");

        $monthRevenue = 0;
        $chartLabels = [];
        $chartData = [];

        if ($isManager) {
            $monthRevenue = Payment::whereMonth("paid_at", now()->month)
                ->whereYear("paid_at", now()->year)
                ->sum("amount");

            // 7 days chart
            for ($i = 6; $i >= 0; $i--) {
                $date = today()->subDays($i);
                $chartLabels[] = $date->format("d M");
                $total = Payment::whereDate("paid_at", $date)->sum("amount");
                $chartData[] = (float) $total;
            }
        }

        $latestOrders = Order::with("customer")
            ->latest()
            ->take(5)
            ->get();

        $resourceAlerts = null;
        if ($isManager) {
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
            ];
        }

        return view("dashboard", compact(
            "isManager", "totalCustomers", "newCustomers", "todayOrders", "processingOrders",
            "readyOrders", "todayRevenue", "monthRevenue",
            "latestOrders", "chartLabels", "chartData", "resourceAlerts"
        ));
    }
}
