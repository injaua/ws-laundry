<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Equipment;
use App\Models\InventoryMovement;
use App\Models\MachineLoad;
use App\Models\Order;
use App\Services\MachineLoads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Machine loads: which machine washed/dried which orders, with what program and who ran it. */
class MachineLoadController extends Controller
{
    public function __construct(private MachineLoads $loads) {}

    public function index(Request $request): View
    {
        Gate::authorize("viewAny", Order::class);

        $loads = MachineLoad::with(["equipment:id,name,capacity_kg", "employee:id,name"])->withCount("orders")
            ->when($request->filled("status"), fn ($q) => $q->where("status", $request->input("status")))
            ->when($request->filled("equipment"), fn ($q) => $q->where("equipment_id", $request->integer("equipment")))
            ->latest("started_at")->latest("id")
            ->paginate(25)->withQueryString();

        return view("production.loads.index", [
            "loads" => $loads,
            "running" => MachineLoad::inProgress()->with(["equipment:id,name,capacity_kg", "employee:id,name", "orders:id,order_number"])->get(),
            "machines" => Equipment::whereIn("type", ["washer", "dryer"])->orderBy("name")->get(["id", "name", "type"]),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize("viewAny", Order::class);

        $stage = in_array($request->input("stage"), MachineLoad::STAGES, true) ? $request->input("stage") : "washing";
        $maxRank = array_search($stage, Order::STATUSES, true);
        $busy = MachineLoad::inProgress()->pluck("equipment_id");

        // Orders that can go in: not past this stage and not already in a running load.
        $orders = Order::inProduction()
            ->whereIn("status", array_slice(Order::STATUSES, 0, $maxRank + 1))
            ->whereDoesntHave("machineLoads", fn ($q) => $q->where("status", "in_progress"))
            ->with(["customer:id,name", "items"])
            ->get()
            ->sortBy(fn (Order $o) => [$o->is_express ? 0 : 1, $o->delivery_date?->timestamp ?? PHP_INT_MAX, $o->id])
            ->values();

        return view("production.loads.create", [
            "stage" => $stage,
            "orders" => $orders,
            "selected" => array_map("intval", (array) $request->input("orders", [])),
            "machines" => Equipment::where("type", MachineLoad::MACHINE_TYPES[$stage])->where("status", "operational")
                ->whereNotIn("id", $busy)->orderBy("name")->get(),
            "employees" => ProductionController::employeesForSelect(),
            "myEmployeeId" => Employee::where("user_id", $request->user()->id)->value("id"),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "stage" => ["required", Rule::in(MachineLoad::STAGES)],
            "equipment_id" => "required|integer|exists:equipments,id",
            "program" => "nullable|string|max:60",
            "employee_id" => "nullable|integer|exists:employees,id",
            "orders" => "required|array|min:1",
            "orders.*" => "nullable|numeric|min:0|max:1000",
            "overload_note" => "nullable|string|max:255",
            "notes" => "nullable|string|max:1000",
        ]);

        $load = $this->loads->start(Equipment::findOrFail($data["equipment_id"]), $data["stage"], $data["orders"], $request->user(), $data);

        return redirect()->route("production.loads.show", $load)->with("success", __("app.load_started", ["code" => $load->code]));
    }

    public function show(MachineLoad $load): View
    {
        Gate::authorize("viewAny", Order::class);

        return view("production.loads.show", [
            "load" => $load->load(["equipment", "employee", "user:id,name", "orders.customer:id,name"]),
            "movements" => InventoryMovement::with("item:id,name,unit")
                ->where("source_type", "machine_load")->where("source_id", $load->id)->get(),
        ]);
    }

    public function finish(Request $request, MachineLoad $load): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $result = $this->loads->finish($load, $request->user());

        $response = back()->with("success", __("app.load_finished", ["code" => $load->code]));

        $warnings = [];
        if ($result["maintenance"]) {
            $warnings[] = __("app.service_due_created", ["machine" => $load->equipment?->name]);
        }
        if ($result["shortfalls"]) {
            $warnings[] = __("app.load_stock_shortfall");
        }

        return $warnings ? $response->with("warning", implode(" ", $warnings)) : $response;
    }

    public function cancel(MachineLoad $load): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $this->loads->cancel($load);

        return back()->with("success", __("app.load_cancelled", ["code" => $load->code]));
    }
}
