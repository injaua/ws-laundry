<?php

namespace App\Http\Controllers;

use App\Exports\OrderExport;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = request_date("from", today()->startOfMonth());
        $to = request_date("to", today());

        $orders = Order::with("customer")
            ->whereBetween("received_at", [$from, $to])
            ->orderBy("received_at")
            ->get();

        $totalOrders = $orders->count();

        // Revenue is computed from orders within the received_at range, not from payment date
        $orderIds = $orders->pluck('id');
        $payments = Payment::whereIn('order_id', $orderIds)->get();
        // Refunds count as negative receipts of the order they belong to.
        $refundsByOrderId = CreditNote::valid()->refunds()->whereIn('order_id', $orderIds)
            ->selectRaw('order_id, sum(amount) as total')->groupBy('order_id')->pluck('total', 'order_id');
        $totalRevenue = Money::sub(Money::sum($payments->pluck('amount')), Money::sum($refundsByOrderId));

        $avgOrder = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        // Chart data by date — revenue from orders received on that date
        $chartLabels = [];
        $chartData = [];
        $paymentsByOrderId = $payments->groupBy('order_id');
        $period = $from->copy();
        while ($period->lte($to)) {
            $chartLabels[] = $period->format("d M");
            $dateKey = $period->format('Y-m-d');
            $dayOrders = $orders->filter(fn ($o) => $o->received_at->format('Y-m-d') === $dateKey);
            $dayTotals = [];
            foreach ($dayOrders as $order) {
                $dayTotals[] = Money::sub(Money::sum(($paymentsByOrderId->get($order->id) ?? collect())->pluck('amount')), $refundsByOrderId[$order->id] ?? 0);
            }
            $chartData[] = Money::sum($dayTotals);
            $period->addDay();
        }

        return view("reports.index", compact(
            "orders", "totalOrders", "totalRevenue", "avgOrder",
            "chartLabels", "chartData", "from", "to"
        ));
    }

    public function export(Request $request)
    {
        $from = request_date("from", today()->startOfMonth());
        $to = request_date("to", today());

        return Excel::download(new OrderExport($from, $to), "report-laundry-{$from->format("Y-m-d")}-{$to->format("Y-m-d")}.xlsx");
    }

    public function profitLoss(Request $request)
    {
        $from = request_date("from", today()->startOfMonth());
        $to = request_date("to", today());

        // Revenue from orders within the range (net of VAT, like the accounting DR)
        $orders = Order::whereBetween("received_at", [$from, $to])->get();
        $totalRevenue = $orders->sum(fn ($o) => (float) ($o->subtotal ?? $o->total_price));
        $totalOrders = $orders->count();

        // Expenses within the range
        $expenses = Expense::whereBetween("expense_date", [$from, $to])->get();
        $totalExpenses = $expenses->sum("amount");

        $netProfit = $totalRevenue - $totalExpenses;
        $margin = $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 1) : 0;

        // Group expenses by category for chart
        $categoryNames = ExpenseCategory::pluck("name", "key");
        $expenseCategories = $expenses->groupBy("category")->map(fn ($g) => $g->sum("amount"));
        $expCatLabels = $expenseCategories->keys()
            ->map(fn ($key) => $categoryNames[$key] ?? $key)
            ->toArray();
        $expCatValues = $expenseCategories->values()->toArray();

        // Daily revenue vs expense for chart
        $chartLabels = [];
        $chartRevenue = [];
        $chartExpense = [];
        $period = $from->copy();
        while ($period->lte($to)) {
            $dateKey = $period->format("Y-m-d");
            $chartLabels[] = $period->format("d M");

            $dayOrders = $orders->filter(fn ($o) => $o->received_at->format("Y-m-d") === $dateKey);
            $chartRevenue[] = Money::round($dayOrders->sum(fn ($o) => (float) ($o->subtotal ?? $o->total_price)));

            $dayExpense = $expenses->filter(fn ($e) => $e->expense_date->format("Y-m-d") === $dateKey);
            $chartExpense[] = Money::round($dayExpense->sum("amount"));

            $period->addDay();
        }

        return view("reports.profit-loss", compact(
            "totalRevenue", "totalOrders", "totalExpenses", "netProfit", "margin",
            "expCatLabels", "expCatValues", "chartLabels", "chartRevenue", "chartExpense",
            "expenses", "from", "to"
        ));
    }

    public function paymentMethods(Request $request)
    {
        $from = request_date("from", today()->startOfMonth());
        $to = request_date("to", today());

        $payments = Payment::whereBetween("paid_at", [$from, $to])->get();
        $refunds = CreditNote::valid()->refunds()->whereDate("issued_at", ">=", $from->toDateString())->whereDate("issued_at", "<=", $to->toDateString())
            ->selectRaw("refund_method, sum(amount) as total")->groupBy("refund_method")->pluck("total", "refund_method");
        $totalReceived = Money::sub(Money::sum($payments->pluck("amount")), Money::sum($refunds));
        $totalCount = $payments->count();

        $rows = [];
        $pieLabels = [];
        $pieValues = [];
        foreach (Payment::METHODS as $method) {
            $group = $payments->where("method", $method);
            if ($group->isEmpty() && !isset($refunds[$method])) {
                continue;
            }
            $amount = Money::sub(Money::sum($group->pluck("amount")), $refunds[$method] ?? 0);
            $rows[] = [
                "method" => $method,
                "count" => $group->count(),
                "amount" => $amount,
                "share" => $totalReceived > 0 ? round($amount / $totalReceived * 100, 1) : 0,
            ];
            $pieLabels[] = __("app.$method");
            $pieValues[] = $amount;
        }

        return view("reports.payment-methods", compact(
            "rows", "pieLabels", "pieValues", "totalReceived", "totalCount", "from", "to"
        ));
    }
}
