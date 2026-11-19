<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Production;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Reclamações: register, follow up within the deadline, resolve. */
class ComplaintController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize("viewAny", Order::class);

        $status = $request->query("status", "pending");
        $search = trim((string) $request->query("search"));

        $complaints = Complaint::with(["customer:id,name", "order:id,order_number", "opener:id,name"])
            ->when($status === "pending", fn ($q) => $q->pending())
            ->when($status === "late", fn ($q) => $q->pending()->whereDate("due_at", "<", today()))
            ->when(in_array($status, Complaint::STATUSES, true), fn ($q) => $q->where("status", $status))
            ->when($search !== "", fn ($q) => $q->where(fn ($q) => $q->where("number", "like", "%{$search}%")
                ->orWhere("description", "like", "%{$search}%")
                ->orWhereHas("customer", fn ($q) => $q->where("name", "like", "%{$search}%"))
                ->orWhereHas("order", fn ($q) => $q->where("order_number", "like", "%{$search}%"))))
            ->orderByRaw("case when status in ('open','in_progress') then 0 else 1 end")
            ->orderBy("due_at")
            ->latest("id")
            ->paginate(25)->withQueryString();

        $since = now()->subDays(90);
        $closed = Complaint::whereIn("status", ["resolved", "rejected"])->where("resolved_at", ">=", $since)->get(["created_at", "resolved_at", "category"]);

        return view("complaints.index", [
            "complaints" => $complaints,
            "status" => $status,
            "search" => $search,
            "stats" => [
                "pending" => Complaint::pending()->count(),
                "late" => Complaint::pending()->whereDate("due_at", "<", today())->count(),
                "avg_days" => $closed->isEmpty() ? null : round($closed->avg(fn ($c) => $c->created_at->diffInHours($c->resolved_at) / 24), 1),
                "by_category" => Complaint::where("created_at", ">=", $since)->selectRaw("category, count(*) as total")
                    ->groupBy("category")->orderByDesc("total")->pluck("total", "category"),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize("viewAny", Order::class);

        $order = $request->integer("order") ? Order::with("customer", "pieces")->find($request->integer("order")) : null;

        return view("complaints.create", [
            "order" => $order,
            "customers" => $order ? collect() : Customer::orderBy("name")->get(["id", "name", "phone"]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "order_id" => "nullable|integer|exists:orders,id",
            "customer_id" => "required_without:order_id|nullable|integer|exists:customers,id",
            "order_piece_id" => ["nullable", "integer", Rule::exists("order_pieces", "id")->where("order_id", $request->integer("order_id"))],
            "channel" => ["required", Rule::in(Complaint::CHANNELS)],
            "category" => ["required", Rule::in(Complaint::CATEGORIES)],
            "description" => "required|string|max:2000",
            "stage" => ["nullable", Rule::in(Order::PRODUCTION_STAGES)],
        ]);

        $order = !empty($data["order_id"]) ? Order::find($data["order_id"]) : null;

        // Link the complaint to where the problem most likely happened: the order's last
        // machine load of that stage (washing by default) and its operator.
        if ($order) {
            $data += $this->guessAttribution($order, $data["stage"] ?? null, $data["category"]);
        }

        // Number and record in one transaction: a failed save gives the number back.
        $complaint = DB::transaction(fn () => Complaint::create(array_merge($data, [
            "number" => Complaint::nextNumber(),
            "customer_id" => $order?->customer_id ?? $data["customer_id"],
            "status" => "open",
            "due_at" => today()->addDays(Complaint::DEADLINE_DAYS),
            "opened_by" => $request->user()->id,
        ])));

        // A piece reported missing or damaged carries the problem on its tracking record.
        if ($complaint->piece && in_array($complaint->category, ["lost_item", "damage", "stain"], true) && blank($complaint->piece->issue)) {
            $complaint->piece->update(["issue" => __("app.complaint_category_" . $complaint->category) . " (" . $complaint->number . ")"]);
        }

        return redirect()->route("complaints.show", $complaint)->with("success", __("app.complaint_created", ["number" => $complaint->number]));
    }

    public function show(Complaint $complaint): View
    {
        Gate::authorize("viewAny", Order::class);

        return view("complaints.show", [
            "complaint" => $complaint->load(["customer", "order", "piece", "opener", "resolver", "creditNotes", "machineLoad.equipment", "employee", "reworks"]),
            "orderLoads" => $complaint->order ? $complaint->order->machineLoads()->with("equipment:id,name")->get() : collect(),
            "employees" => ProductionController::employeesForSelect(),
            "reworkOrder" => $complaint->order_id ? Order::where("rework_of_order_id", $complaint->order_id)->latest("id")->first() : null,
        ]);
    }

    /** Where it went wrong: stage, machine load and employee (quality figures by stage/machine/operator). */
    public function attribution(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "stage" => ["nullable", Rule::in(Order::PRODUCTION_STAGES)],
            "machine_load_id" => ["nullable", "integer", Rule::exists("machine_load_order", "machine_load_id")->where("order_id", $complaint->order_id)],
            "employee_id" => "nullable|integer|exists:employees,id",
        ]);

        $complaint->update([
            "stage" => $data["stage"] ?? null,
            "machine_load_id" => $data["machine_load_id"] ?? null,
            "employee_id" => $data["employee_id"] ?? null,
        ]);

        return back()->with("success", __("app.complaint_updated"));
    }

    /**
     * Re-wash: an order still in the shop goes back to washing; a delivered one gets a
     * new free order (the customer brings the clothes back).
     */
    public function rework(Request $request, Complaint $complaint, Production $production): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $order = $complaint->order;
        abort_unless($order, 404);

        if ($order->status === "completed") {
            $new = $production->reworkOrderFromComplaint($complaint, $request->user());

            return redirect()->route("orders.show", $new)->with("success", __("app.rework_order_created", ["order" => $new->order_number]));
        }

        $production->rework($order, $request->user(), $complaint->description ?: __("app.complaint_category_" . $complaint->category), "complaint", $complaint);

        return back()->with("success", __("app.rework_started", ["order" => $order->order_number]));
    }

    private function guessAttribution(Order $order, ?string $stage, string $category): array
    {
        if (in_array($category, ["billing", "delay", "service"], true) && !$stage) {
            return [];
        }

        $loadStage = in_array($stage, ["washing", "drying"], true) ? $stage : ($stage ? null : "washing");
        $load = $loadStage
            ? $order->machineLoads()->where("stage", $loadStage)->where("machine_loads.status", "done")->latest("machine_loads.finished_at")->first()
            : null;

        $employeeId = $load?->employee_id;
        if (!$employeeId && $stage) {
            // Who finished that stage on the board.
            $employeeId = $order->stageLogs()->where("from_status", $stage)->whereNotNull("employee_id")->latest("id")->value("employee_id");
        }

        return array_filter([
            "stage" => $stage ?? ($load ? $loadStage : null),
            "machine_load_id" => $load?->id,
            "employee_id" => $employeeId,
        ]);
    }

    public function update(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "status" => ["required", Rule::in(Complaint::STATUSES)],
            "resolution" => ["nullable", "required_if:status,resolved", "required_if:status,rejected", Rule::in(Complaint::RESOLUTIONS)],
            "resolution_notes" => "nullable|string|max:2000",
            "due_at" => "nullable|date",
        ]);

        $closing = in_array($data["status"], ["resolved", "rejected"], true);

        $complaint->update([
            "status" => $data["status"],
            "resolution" => $data["status"] === "rejected" ? "rejected" : ($data["resolution"] ?? null),
            "resolution_notes" => $data["resolution_notes"] ?? $complaint->resolution_notes,
            "due_at" => $data["due_at"] ?? $complaint->due_at,
            "resolved_by" => $closing ? ($complaint->resolved_by ?? $request->user()->id) : null,
            "resolved_at" => $closing ? ($complaint->resolved_at ?? now()) : null,
        ]);

        return redirect()->route("complaints.show", $complaint)->with("success", __("app.complaint_updated"));
    }
}
