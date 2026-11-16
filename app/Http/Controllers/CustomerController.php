<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($search = $request->search) {
            $query->where("name", "like", "%{$search}%")
                  ->orWhere("phone", "like", "%{$search}%");
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

        return view("customers.show", compact("customer", "orders"));
    }

    public function create()
    {
        return view("customers.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "phone" => "required|string|max:20|unique:customers",
            "address" => "nullable|string",
        ]);

        Customer::create($validated);

        return redirect()->route("customers.index")
            ->with("success", __('app.customer_added'));
    }

    public function edit(Customer $customer)
    {
        return view("customers.edit", compact("customer"));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "phone" => ["required", "string", "max:20", Rule::unique("customers")->ignore($customer->id)],
            "address" => "nullable|string",
        ]);

        $customer->update($validated);

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
