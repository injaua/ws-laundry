<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\LoyaltyPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Package;
use App\Models\Service;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Milon\Barcode\DNS1D;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with("customer");

        if ($search = $request->search) {
            $query->where("order_number", "like", "%{$search}%")
                  ->orWhereHas("customer", fn($q) => $q->where("name", "like", "%{$search}%"));
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
        $ivaRate = (float) SiteSetting::get("iva_rate", 16);
        return view("orders.create", compact("customers", "services", "packages", "ivaRate"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "customer_id" => "required|exists:customers,id",
            "received_at" => "required|date",
            "delivery_date" => "nullable|date|after_or_equal:received_at",
            "notes" => "nullable|string",
            "items" => "required|array|min:1",
            "items.*.service_id" => "nullable|exists:services,id",
            "items.*.package_id" => "nullable|exists:packages,id",
            "items.*.weight" => "nullable|numeric|min:0.5",
            "items.*.amount" => "nullable|integer|min:1",
        ]);

        // validated() may drop the items array when an item carries no rule-bearing keys
        $items = $request->input("items", []);

        foreach ($items as $key => $item) {
            if (!is_array($item) || (empty($item["service_id"]) && empty($item["package_id"]))) {
                throw ValidationException::withMessages([
                    "items.$key.service_id" => __('app.select_service_in_every_item'),
                ]);
            }
        }

        // Prices and totals always come from the catalog, never from the browser.
        $rows = [];
        foreach ($items as $item) {
            $service = !empty($item["service_id"]) ? Service::find($item["service_id"]) : null;
            $package = !$service && !empty($item["package_id"]) ? Package::find($item["package_id"]) : null;

            if ($service) {
                $quantity = $service->type === "by_weight"
                    ? max(0.5, (float) ($item["weight"] ?? 0))
                    : max(1, (int) ($item["amount"] ?? 1));
                $rows[] = [
                    "service" => $service,
                    "package" => null,
                    "type" => $service->type,
                    "weight" => $service->type === "by_weight" ? $quantity : null,
                    "amount" => $service->type === "by_piece" ? (int) $quantity : 1,
                    "unit_price" => (float) $service->price,
                    "subtotal" => round((float) $service->price * $quantity),
                ];
            } else {
                $quantity = max(1, (int) ($item["amount"] ?? 1));
                $rows[] = [
                    "service" => null,
                    "package" => $package,
                    "type" => "by_package",
                    "weight" => round((float) $package->weight_kg * $quantity, 2),
                    "amount" => $quantity,
                    "unit_price" => (float) $package->price,
                    "subtotal" => round((float) $package->price * $quantity),
                ];
            }
        }

        try {
            DB::beginTransaction();

            // Generate unique order number
            $orderNumber = "ORD" . date("ymd") . str_pad(Order::withTrashed()->whereDate("created_at", today())->count() + 1, 3, "0", STR_PAD_LEFT);

            // Auto-fill delivery date from the expected completion date (max service lead time)
            $deliveryDate = $validated["delivery_date"] ?? null;
            if (!$deliveryDate) {
                $days = Service::whereIn("id", collect($items)->pluck("service_id")->filter())->max("estimated_days");
                if ($days) {
                    $deliveryDate = now()->parse($validated["received_at"])->addDays((int) $days)->toDateString();
                }
            }

            $subtotal = array_sum(array_column($rows, "subtotal"));
            $ivaRate = (float) SiteSetting::get("iva_rate", 16);
            $ivaAmount = round($subtotal * $ivaRate / 100);

            $order = Order::create([
                "order_number" => $orderNumber,
                "customer_id" => $validated["customer_id"],
                "received_at" => $validated["received_at"],
                "delivery_date" => $deliveryDate,
                "subtotal" => $subtotal,
                "iva_rate" => $ivaRate,
                "iva_amount" => $ivaAmount,
                "total_price" => $subtotal + $ivaAmount,
                "notes" => $validated["notes"] ?? null,
                "status" => "received",
            ]);

            foreach ($rows as $row) {
                OrderItem::create([
                    "order_id" => $order->id,
                    "service_id" => $row["service"]?->id,
                    "package_id" => $row["package"]?->id,
                    "type" => $row["type"],
                    "weight" => $row["weight"],
                    "amount" => $row["amount"],
                    "unit_price" => $row["unit_price"],
                    "subtotal" => $row["subtotal"],
                ]);
            }

            DB::commit();

            return redirect()->route("orders.show", $order)
                ->with("success", __('app.order_created_success', ['number' => $orderNumber]));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with("error", __('app.order_create_failed', ['reason' => $e->getMessage()]))
                ->withInput();
        }
    }

    public function show(Order $order)
    {
        $order->load(["customer", "items.service", "items.package", "payments"]);
        return view("orders.show", compact("order"));
    }

    public function edit(Order $order)
    {
        $customers = Customer::orderBy("name")->get();
        $services = Service::orderBy("name")->get();
        return view("orders.edit", compact("order", "customers", "services"));
    }

    public function update(Request $request, Order $order)
    {
        $wasCompleted = $order->status === "completed";

        if ($wasCompleted) {
            return redirect()->route("orders.show", $order)
                ->with("error", __('app.order_locked_error'));
        }

        $validated = $request->validate([
            "customer_id" => "required|exists:customers,id",
            "status" => "required|in:" . implode(",", Order::STATUSES),
            "received_at" => "required|date",
            "delivery_date" => "nullable|date|after_or_equal:received_at",
            "completed_at" => "nullable|date",
            "notes" => "nullable|string",
        ]);

        // Single flow: the clothing state may only move forward, received -> completed
        if (!$order->canTransitionTo($validated["status"])) {
            return back()->withInput()
                ->with("error", __('app.status_cannot_go_back'));
        }

        // Store (cashier) accounts may only advance the order status, never alter its data
        $isCashier = $request->user()->isCashier();

        if ($isCashier && (
            (int) $validated["customer_id"] !== (int) $order->customer_id
            || $request->date("received_at")->toDateString() !== $order->received_at->toDateString()
            || ($request->has("notes") && $request->input("notes") !== $order->notes)
        )) {
            return back()->with("error", __('app.cashier_status_only'));
        }

        $isCashier
            ? $order->update(["status" => $validated["status"], "completed_at" => $validated["completed_at"] ?? null])
            : $order->update($validated);

        // Award loyalty points once, when the order transitions to completed
        if (!$wasCompleted && $order->status === "completed") {
            $order->customer->earnPoints($order);
        }

        return redirect()->route("orders.show", $order)
            ->with("success", __('app.order_status_updated'));
    }

    public function destroy(Order $order)
    {
        $order->delete();
        return redirect()->route("orders.index")
            ->with("success", __('app.order_deleted'));
    }

    public function payment(Order $order)
    {
        $order->load("payments");
        return view("orders.payment", compact("order"));
    }

    public function invoice(Order $order)
    {
        $order->load(["customer", "items.service", "items.package", "payments"]);
        return view("orders.invoice", compact("order"));
    }

    public function label(Order $order)
    {
        $order->load(["customer", "items"]);

        try {
            $barcode = new DNS1D();
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
        $order->restore();

        return redirect()->route("orders.trash")
            ->with("success", __('app.order_restored'));
    }

}