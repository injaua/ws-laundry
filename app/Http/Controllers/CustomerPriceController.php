<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Package;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Negotiated prices per customer (Phase 4). Management only. */
class CustomerPriceController extends Controller
{
    public function store(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            "item" => ["required", "regex:/^[sp]\d+$/"],
            "price" => "required|numeric|min:0",
        ]);

        $column = $validated["item"][0] === "s" ? "service_id" : "package_id";
        $id = (int) substr($validated["item"], 1);
        $model = $column === "service_id" ? Service::class : Package::class;

        if (!$model::whereKey($id)->exists()) {
            throw ValidationException::withMessages(["item" => __("validation.exists", ["attribute" => __("app.service")])]);
        }

        CustomerPrice::updateOrCreate(
            ["customer_id" => $customer->id, "service_id" => $column === "service_id" ? $id : null, "package_id" => $column === "package_id" ? $id : null],
            ["price" => $validated["price"]]
        );

        return redirect()->route("customers.show", $customer)->with("success", __("app.customer_price_saved"));
    }

    public function destroy(Customer $customer, CustomerPrice $price)
    {
        abort_unless($price->customer_id === $customer->id, 404);

        $price->delete();

        return redirect()->route("customers.show", $customer)->with("success", __("app.customer_price_deleted"));
    }
}
