<?php

namespace App\Http\Controllers;

use App\Exports\ReceivablesExport;
use App\Models\Customer;
use App\Models\Order;
use App\Services\AccountsReceivable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AccountsReceivableController extends Controller
{
    public function __construct(private AccountsReceivable $ar) {}

    public function index(Request $request)
    {
        $orders = $this->ar->outstandingOrders();

        if ($search = $request->search) {
            $orders = $orders->filter(function (Order $order) use ($search) {
                return stripos($order->order_number, $search) !== false
                    || stripos($order->customer?->name ?? "", $search) !== false
                    || stripos($order->customer?->phone ?? "", $search) !== false;
            });
        }

        if ($request->integer("customer_id")) {
            $orders = $orders->where("customer_id", $request->integer("customer_id"));
        }

        if ($request->status === "overdue") {
            $orders = $orders->filter(fn (Order $order) => $order->isOverdue());
        }

        $orders = $orders->values();
        $summary = $this->ar->summary($orders);
        $aging = $this->ar->agingByCustomer($orders);
        $creditCustomers = Customer::where("is_credit_customer", true)->orderBy("name")->get();

        return view("receivables.index", compact("orders", "summary", "aging", "creditCustomers"));
    }

    public function customer(Request $request, Customer $customer)
    {
        abort_unless($customer->is_credit_customer, 404);

        $orders = $customer->orders()
            ->where("is_credit", true)
            ->with(["payments", "invoice"])
            ->orderBy("received_at")
            ->get();

        $summary = $this->ar->summary($orders->filter(fn (Order $order) => $order->remainingBalance() > 0));

        $payable = $customer->creditOutstandingBalance();
        $invoices = $customer->invoices()->with(["orders.payments", "orders.creditNotes"])->latest("id")->limit(12)->get();

        return view("receivables.customer", compact("customer", "orders", "summary", "invoices", "payable"));
    }

    public function export()
    {
        return Excel::download(
            new ReceivablesExport($this->ar),
            "receivables-" . now()->toDateString() . ".xlsx"
        );
    }
}
