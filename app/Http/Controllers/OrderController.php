<?php

namespace App\Http\Controllers;

use App\Actions\Orders\CreateOrder;
use App\Actions\Orders\UpdateOrder;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\CashSession;
use App\Models\CourierJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\Pricing;
use App\Services\ProductionPlanner;
use App\Support\Documents\DocumentResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Milon\Barcode\DNS1D;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with("customer");

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where("order_number", "like", "%{$search}%")
                    ->orWhereHas("customer", fn ($c) => $c->where("name", "like", "%{$search}%"));
            });
        }

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        $orders = $query->latest()->paginate(15);

        return view("orders.index", compact("orders"));
    }

    public function create()
    {
        $customers = Customer::orderBy("name")->get();
        $services = Service::orderBy("name")->get();
        $packages = Package::where("is_active", true)->orderBy("name")->get();
        $vatRate = (float) SiteSetting::get("vat_rate", 16);

        $customerPricing = Customer::pricingMap();
        $promotions = Pricing::promotionsForOrderForm();
        $express = Pricing::expressSettings();

        // Order for clothes collected at home: the pickup fee is billed on it.
        $pickupId = request()->integer("pickup") ?: (int) old("pickup_job_id");
        $pickup = $pickupId ? CourierJob::with(["customer", "zone"])->find($pickupId) : null;
        if ($pickup && !$pickup->awaitsOrder()) {
            $pickup = null;
        }

        // For resources/js/pages/order-form.js (only the fields it reads).
        $formData = [
            "services" => $services->map->only(["id", "name", "type", "price", "estimated_days"])->values(),
            "packages" => $packages->map->only(["id", "name", "price", "weight_kg"])->values(),
            "customerPricing" => (object) $customerPricing,
            "promotions" => $promotions,
            "express" => $express,
            "pickup" => $pickup ? ["fee" => (float) $pickup->fee, "free_from" => $pickup->zone?->free_from !== null ? (float) $pickup->zone->free_from : null] : null,
            "vatRate" => $vatRate,
            "planning" => ProductionPlanner::enabled(),
            "oldItems" => old("items"),
            "urls" => ["promise" => route("production.promise"), "create" => route("orders.create")],
            "texts" => [
                "service" => __('app.service'), "packages" => __('app.packages'), "pieces" => __('app.pieces'),
                "byPackage" => __('app.by_package'), "selectService" => __('app.select_service'),
                "promiseHint" => __('app.promise_hint'), "creditTerms" => __('app.credit_terms_days'),
                "creditLimit" => __('app.credit_limit'), "unlimited" => __('app.unlimited'),
            ],
        ];

        return view("orders.create", compact("customers", "vatRate", "express", "pickup", "formData"));
    }

    public function store(StoreOrderRequest $request, CreateOrder $createOrder)
    {
        try {
            $order = $createOrder->handle($request->validated(), $request->orderItems(), $request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Business rules fail as ValidationException (above). Anything else is technical
            // (database, deadlock…): logged in full, never shown — it may contain SQL and data.
            report($e);

            return back()->with("error", __('app.order_create_failed'))->withInput();
        }

        // Intake photos: the state of the clothes on arrival.
        foreach ((array) $request->file("photos", []) as $photo) {
            $order->photos()->create(["path" => $photo->store("order-photos", "public"), "user_id" => $request->user()->id]);
        }

        return redirect()->route("orders.show", $order)
            ->with("success", __('app.order_created_success', ['number' => $order->order_number]));
    }

    public function show(Order $order)
    {
        $order->load(["customer", "items.service", "items.package", "items.promotion", "payments.cheque", "bouncedPayments.cheque", "courierJobs.courier:id,name", "pieces.events.user:id,name", "creditNotes.user:id,name", "creditNotes.mobileRefund", "complaints",
            "stageLogs.user:id,name", "stageLogs.employee:id,name", "photos", "reworks.user:id,name", "machineLoads.equipment", "machineLoads.employee:id,name", "qcUser:id,name", "reworkOf:id,order_number"]);

        return view("orders.show", compact("order"));
    }

    public function edit(Order $order)
    {
        Gate::authorize("edit", $order);

        $customers = Customer::orderBy("name")->get();
        $services = Service::orderBy("name")->get();

        return view("orders.edit", compact("order", "customers", "services"));
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrder $updateOrder)
    {
        $result = $updateOrder->handle($order, $request->validated(), $request->user(), $request->all());

        if ($result["error"]) {
            return $order->status === "completed"
                ? redirect()->route("orders.show", $order)->with("error", $result["error"])
                : back()->withInput()->with("error", $result["error"]);
        }

        $redirect = redirect()->route("orders.show", $order)
            ->with("success", __('app.order_status_updated'));

        return $result["warnings"]
            ? $redirect->with("warning", implode(" ", $result["warnings"]))
            : $redirect;
    }

    public function destroy(Order $order)
    {
        Gate::authorize("delete", $order);

        if ($order->invoice_id) {
            return redirect()->route("orders.show", $order)
                ->with("error", __("app.order_on_invoice_locked", ["invoice" => $order->invoice?->label()]));
        }

        $order->delete();

        return redirect()->route("orders.index")
            ->with("success", __('app.order_deleted'));
    }

    public function payment(Order $order)
    {
        $order->load("payments");
        $till = CashSession::currentFor(auth()->user());
        $paymentMethods = Payment::formMethods($order->customer);

        return view("orders.payment", compact("order", "till", "paymentMethods"));
    }

    public function invoice(Order $order)
    {
        $order->load(["customer", "items.service", "items.package", "items.promotion", "payments.cheque"]);

        return view("orders.invoice", compact("order"));
    }

    /** A4 invoice of the order (HTML, or PDF with ?format=pdf). */
    public function document(Order $order)
    {
        $order->load(["customer", "items.service", "items.package", "items.promotion", "payments.cheque"]);

        return DocumentResponse::make("documents.order", compact("order"), __("app.invoice") . " " . $order->order_number);
    }

    public function label(Order $order)
    {
        $order->load(["customer", "items"]);

        try {
            $barcode = new DNS1D;
            $barcodeSvg = $barcode->getBarcodeSVG($order->order_number, "C128", 2, 45, "black", true);
        } catch (\Exception $e) {
            $barcodeSvg = null;
        }

        return view("orders.label", compact("order", "barcodeSvg"));
    }

    public function trash()
    {
        $orders = Order::onlyTrashed()
            ->with("customer")
            ->latest("deleted_at")
            ->paginate(15);

        return view("orders.trash", compact("orders"));
    }

    public function restore($id)
    {
        $order = Order::onlyTrashed()->findOrFail($id);
        Gate::authorize("restore", $order);

        $order->restore();

        return redirect()->route("orders.trash")
            ->with("success", __('app.order_restored'));
    }
}
