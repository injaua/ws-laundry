<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Service;
use App\Services\Security\CustomerPrivacy;
use App\Support\Money;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($search = $request->search) {
            $query->where("name", "like", "%{$search}%")
                ->orWhere("phone", "like", "%{$search}%")
                ->orWhere("legal_name", "like", "%{$search}%")
                ->orWhere("nuit", "like", "%{$search}%");
        }

        $customers = $query->withCount("orders")
            ->latest()
            ->paginate(15);

        return view("customers.index", compact("customers"));
    }

    public function show(Customer $customer)
    {
        $customer->loadCount("orders");

        $orders = $customer->orders()
            ->with("customer")
            ->latest()
            ->paginate(15);

        $receivables = null;
        if ($customer->is_credit_customer) {
            $overdue = $customer->orders()->overdueCredit()->get();
            $receivables = [
                "outstanding" => $customer->creditOutstandingBalance(),
                "overdue_count" => $overdue->count(),
                "overdue_amount" => Money::round($overdue->sum(fn ($order) => $order->remainingBalance())),
            ];
        }

        $messageLogs = $customer->messageLogs()->with("sender:id,name")->latest()->limit(10)->get();

        $customer->load(["prices.service", "prices.package"]);
        $invoices = $customer->is_credit_customer
            ? $customer->invoices()->with(["orders.payments", "orders.creditNotes"])->latest("id")->limit(5)->get()
            : collect();
        $services = Service::orderBy("name")->get();
        $packages = Package::orderBy("name")->get();

        $privacyBlockers = auth()->user()->isAdmin() || auth()->user()->isOwner()
            ? app(CustomerPrivacy::class)->blockers($customer)
            : [];

        return view("customers.show", compact("customer", "orders", "receivables", "messageLogs", "invoices", "services", "packages", "privacyBlockers"));
    }

    public function create()
    {
        return view("customers.create");
    }

    public function store(CustomerRequest $request)
    {
        Customer::create($request->customerData());

        return redirect()->route("customers.index")
            ->with("success", __('app.customer_added'));
    }

    public function edit(Customer $customer)
    {
        return view("customers.edit", compact("customer"));
    }

    public function update(CustomerRequest $request, Customer $customer)
    {
        $wasMonthly = $customer->billsMonthly();
        $customer->update($request->customerData());

        // Leaving monthly billing: unbilled orders get their own due date again,
        // otherwise they would never become overdue.
        if ($wasMonthly && !$customer->billsMonthly()) {
            $customer->orders()->outstandingCredit()->whereNull("invoice_id")->whereNull("due_date")->get()
                ->each(fn (Order $order) => $order->update([
                    "due_date" => ($order->completed_at ?? $order->received_at)->copy()->addDays($customer->credit_terms_days ?: 30)->toDateString(),
                ]));
        }

        return redirect()->route("customers.index")
            ->with("success", __('app.customer_updated'));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->orders()->exists()) {
            return redirect()->route("customers.index")
                ->with("error", __('app.customer_delete_blocked'));
        }

        $customer->delete();

        return redirect()->route("customers.index")
            ->with("success", __('app.customer_deleted'));
    }
}
