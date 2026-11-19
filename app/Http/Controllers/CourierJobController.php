<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\CourierJob;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Logistics;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Counter/management side of home pickup & delivery: planning, assignment and cash handover. */
class CourierJobController extends Controller
{
    public function __construct(private Logistics $logistics) {}

    public function index(Request $request)
    {
        $date = $request->filled("date") ? now()->parse($request->date)->startOfDay() : today();
        $courierId = $request->integer("courier") ?: null;

        $jobs = CourierJob::with(["customer:id,name", "order:id,order_number,status,total_price,is_credit", "zone:id,name", "courier:id,name"])
            ->whereDate("scheduled_date", $date)
            ->when($courierId, fn ($q) => $q->where("courier_id", $courierId))
            ->orderByRaw("CASE time_window WHEN 'morning' THEN 1 WHEN 'afternoon' THEN 2 WHEN 'evening' THEN 3 ELSE 4 END")
            ->orderBy("id")
            ->get();

        // Things that need someone at the counter, whatever the date.
        $requested = CourierJob::with("zone:id,name")->where("status", "requested")->orderBy("scheduled_date")->get();
        $overdue = CourierJob::with(["customer:id,name", "courier:id,name"])
            ->whereIn("status", ["scheduled", "on_the_way", "failed"])
            ->whereDate("scheduled_date", "<", today())
            ->orderBy("scheduled_date")->get();
        $awaitingOrder = CourierJob::with(["customer:id,name", "courier:id,name"])
            ->where("type", "pickup")->where("status", "done")->whereNull("order_id")
            ->orderBy("completed_at")->get();
        $cashPending = (float) Payment::pendingHandover()->sum("amount");

        $couriers = $this->couriers();

        return view("logistics.index", compact("jobs", "date", "courierId", "requested", "overdue", "awaitingOrder", "cashPending", "couriers"));
    }

    public function create(Request $request)
    {
        $type = $request->input("type") === "delivery" ? "delivery" : "pickup";
        $order = $type === "delivery" && $request->filled("order_id")
            ? Order::with("customer.deliveryZone")->findOrFail($request->order_id)
            : null;
        $customer = $order?->customer ?? ($request->filled("customer_id") ? Customer::find($request->customer_id) : null);

        return view("logistics.create", [
            "type" => $type,
            "order" => $order,
            "customer" => $customer,
            "customers" => $type === "pickup" ? Customer::orderBy("name")->get(["id", "name", "phone", "address", "address_reference", "delivery_zone_id"]) : collect(),
            "zones" => DeliveryZone::active()->get(),
            "couriers" => $this->couriers(),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input("type") === "delivery" ? "delivery" : "pickup";
        $data = $request->validate($this->rules($request, $type));

        if (!$request->user()->hasRole(["admin", "owner"])) {
            unset($data["fee"]); // the zone decides; only management overrides fees
        }

        if ($type === "delivery") {
            $order = Order::findOrFail($data["order_id"]);
            $job = $this->logistics->scheduleDelivery($order, $data, $request->user());
        } else {
            $job = $this->logistics->schedulePickup($data, $request->user());
        }

        $this->rememberAddress($job);

        return redirect()->route("logistics.index", ["date" => $job->scheduled_date->toDateString()])
            ->with("success", __("app.courier_job_scheduled"));
    }

    public function show(CourierJob $courierJob)
    {
        $courierJob->load(["customer", "order.customer", "zone", "courier", "creator", "payments.user"]);

        return view("logistics.show", [
            "job" => $courierJob,
            "zones" => DeliveryZone::active()->get(),
            "couriers" => $this->couriers(),
            "customers" => $courierJob->status === "requested" ? Customer::orderBy("name")->get(["id", "name", "phone"]) : collect(),
            "match" => $courierJob->status === "requested" ? Customer::findByPhone($courierJob->contact_phone) : null,
        ]);
    }

    public function update(Request $request, CourierJob $courierJob)
    {
        $data = $request->validate([
            "scheduled_date" => "required|date",
            "time_window" => ["required", Rule::in(CourierJob::WINDOWS)],
            "courier_id" => ["nullable", Rule::exists("users", "id")->where("role", "courier")->where("store_id", CurrentStore::id())],
            "delivery_zone_id" => ["nullable", DeliveryZone::existsRule()],
            "contact_name" => "required|string|max:100",
            "contact_phone" => "required|string|max:30",
            "address" => "required|string|max:255",
            "address_reference" => "nullable|string|max:255",
            "fee" => "nullable|numeric|min:0|max:1000000",
            "notes" => "nullable|string|max:1000",
        ]);

        if (!$request->user()->hasRole(["admin", "owner"]) || ($data["fee"] ?? null) === null) {
            unset($data["fee"]);
        }

        $this->logistics->update($courierJob, $data);

        return redirect()->route("logistics.show", $courierJob)->with("success", __("app.courier_job_updated"));
    }

    public function confirm(Request $request, CourierJob $courierJob)
    {
        $data = $request->validate([
            "customer_id" => "nullable|exists:customers,id",
            "scheduled_date" => "required|date",
            "time_window" => ["required", Rule::in(CourierJob::WINDOWS)],
            "courier_id" => ["nullable", Rule::exists("users", "id")->where("role", "courier")->where("store_id", CurrentStore::id())],
        ]);

        $this->logistics->confirm($courierJob, $data, $request->user());

        return redirect()->route("logistics.show", $courierJob)->with("success", __("app.pickup_request_confirmed"));
    }

    public function cancel(CourierJob $courierJob)
    {
        $this->logistics->cancel($courierJob);

        return redirect()->route("logistics.show", $courierJob)->with("success", __("app.courier_job_cancelled"));
    }

    /** Printable list of a day's trips for one courier (or everyone). */
    public function routeSheet(Request $request)
    {
        $date = $request->filled("date") ? now()->parse($request->date)->startOfDay() : today();
        $courier = $request->filled("courier") ? User::inStore()->find($request->courier) : null;

        $jobs = CourierJob::with(["customer:id,name", "order", "zone:id,name", "courier:id,name"])
            ->whereDate("scheduled_date", $date)
            ->whereIn("status", ["scheduled", "on_the_way"])
            ->when($courier, fn ($q) => $q->where("courier_id", $courier->id))
            ->orderByRaw("CASE time_window WHEN 'morning' THEN 1 WHEN 'afternoon' THEN 2 WHEN 'evening' THEN 3 ELSE 4 END")
            ->orderBy("delivery_zone_id")->orderBy("id")
            ->get();

        return view("logistics.route-sheet", compact("jobs", "date", "courier"));
    }

    /** Cash collected on deliveries that has not reached a till yet. */
    public function cash()
    {
        $pending = $this->logistics->cashToHandOver();
        $holders = User::whereIn("id", $pending->keys())->get()->keyBy("id");
        $till = CashSession::currentFor(auth()->user());

        return view("logistics.cash", compact("pending", "holders", "till"));
    }

    public function receiveCash(Request $request, User $user)
    {
        $amount = $this->logistics->receiveCash($user, $request->user());

        return redirect()->route("logistics.cash")->with("success", __("app.courier_cash_received", [
            "amount" => money($amount),
            "name" => $user->name,
        ]));
    }

    private function couriers()
    {
        return User::inStore()->where("role", "courier")->where("is_active", true)->orderBy("name")->get(["id", "name"]);
    }

    private function rules(Request $request, string $type): array
    {
        $rules = [
            "scheduled_date" => "required|date|after_or_equal:" . today()->subDays(1)->toDateString(),
            "time_window" => ["required", Rule::in(CourierJob::WINDOWS)],
            "courier_id" => ["nullable", Rule::exists("users", "id")->where("role", "courier")->where("store_id", CurrentStore::id())],
            "delivery_zone_id" => ["nullable", DeliveryZone::existsRule()],
            "contact_name" => "nullable|string|max:100",
            "contact_phone" => "nullable|string|max:30",
            "address" => "required|string|max:255",
            "address_reference" => "nullable|string|max:255",
            "fee" => "nullable|numeric|min:0|max:1000000",
            "notes" => "nullable|string|max:1000",
        ];

        return $type === "delivery"
            ? $rules + ["order_id" => "required|exists:orders,id"]
            : $rules + ["customer_id" => "required|exists:customers,id"];
    }

    /** First home trip for a customer without an address on file: keep it for next time. */
    private function rememberAddress(CourierJob $job): void
    {
        $customer = $job->customer;

        if ($customer && !$customer->address) {
            $customer->update([
                "address" => $job->address,
                "address_reference" => $customer->address_reference ?? $job->address_reference,
                "delivery_zone_id" => $customer->delivery_zone_id ?? $job->delivery_zone_id,
            ]);
        }
    }
}
