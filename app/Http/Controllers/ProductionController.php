<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderPhoto;
use App\Services\Production;
use App\Services\ProductionPlanner;
use App\Services\ProductionReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Production floor: the stage board, quality control, rework, delays and indicators. */
class ProductionController extends Controller
{
    public function __construct(private Production $production) {}

    public function board(Request $request): View
    {
        Gate::authorize("viewAny", Order::class);

        $search = trim((string) $request->input("search"));
        $onlyLate = $request->boolean("late");

        $orders = Order::inProduction()
            ->with(["customer:id,name,phone", "items", "stageLogs", "machineLoads" => fn ($q) => $q->where("status", "in_progress")->with("equipment:id,name")])
            ->when($search !== "", fn ($q) => $q->where(fn ($w) => $w->where("order_number", "like", "%{$search}%")
                ->orWhereHas("customer", fn ($c) => $c->where("name", "like", "%{$search}%")->orWhere("phone", "like", "%{$search}%"))))
            ->when($onlyLate, fn ($q) => $q->where(fn ($w) => $w->whereDate("delivery_date", "<=", today())))
            ->get()
            // Express first, then the earliest promise.
            ->sortBy(fn (Order $o) => [$o->is_express ? 0 : 1, $o->delivery_date?->timestamp ?? PHP_INT_MAX, $o->id])
            ->groupBy("status");

        return view("production.board", [
            "columns" => Order::PRODUCTION_STAGES,
            "orders" => $orders,
            "search" => $search,
            "onlyLate" => $onlyLate,
            "lateCount" => Order::late()->count(),
            "dueTodayCount" => Order::dueToday()->count(),
            "readyToday" => Order::whereDate("ready_at", today())->count(),
            "employees" => $this->employees(),
            "myEmployeeId" => Employee::where("user_id", $request->user()->id)->value("id"),
            "qcRequired" => Production::qcRequired(),
            "capacity" => ProductionPlanner::capacity(),
            "queue" => app(ProductionPlanner::class)->queue(),
        ]);
    }

    public function advance(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "employee_id" => "nullable|integer|exists:employees,id",
            "to" => ["nullable", Rule::in([...Order::PRODUCTION_STAGES, "ready"])],
        ]);

        $warnings = $this->production->advance($order, $request->user(), $data["employee_id"] ?? null, $data["to"] ?? null);

        return $this->back($request, $order, __("app.order_moved_to", ["order" => $order->order_number, "stage" => __("app.status_" . $order->fresh()->status)]), $warnings);
    }

    public function qc(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "result" => "required|in:pass,fail",
            "reason" => "required_if:result,fail|nullable|string|max:255",
            "employee_id" => "nullable|integer|exists:employees,id",
        ]);

        if ($data["result"] === "pass") {
            $warnings = $this->production->passQc($order, $request->user(), $data["employee_id"] ?? null);

            return $this->back($request, $order, __("app.qc_passed_msg", ["order" => $order->order_number]), $warnings);
        }

        $this->production->rework($order, $request->user(), $data["reason"], "qc");

        return $this->back($request, $order, __("app.rework_started", ["order" => $order->order_number]));
    }

    public function rework(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate(["reason" => "required|string|max:255"]);
        $this->production->rework($order, $request->user(), $data["reason"], "manual");

        return $this->back($request, $order, __("app.rework_started", ["order" => $order->order_number]));
    }

    public function reschedule(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "delivery_date" => "required|date|after_or_equal:today",
            "reason" => "required|string|max:255",
            "notify" => "nullable|boolean",
        ]);

        $this->production->reschedule($order, $data["delivery_date"], $data["reason"], $request->boolean("notify"));

        return $this->back($request, $order, __("app.order_rescheduled", ["order" => $order->order_number, "date" => Carbon::parse($data["delivery_date"])->format(date_fmt())]));
    }

    /** Suggested promise date for the order form (queue + daily capacity). */
    public function promise(Request $request, ProductionPlanner $planner): JsonResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "received_at" => "nullable|date",
            "kg" => "nullable|numeric|min:0",
            "pieces" => "nullable|integer|min:0",
            "lead_days" => "nullable|integer|min:0|max:365",
            "express" => "nullable|boolean",
            "wanted" => "nullable|date",
        ]);

        return response()->json($planner->promise(
            Carbon::parse($data["received_at"] ?? today()),
            (float) ($data["kg"] ?? 0),
            (int) ($data["pieces"] ?? 0),
            (int) ($data["lead_days"] ?? 0),
            $request->boolean("express"),
            $data["wanted"] ?? null,
        ));
    }

    public function report(Request $request, ProductionReport $report): View
    {
        $from = $request->filled("from") ? Carbon::parse($request->input("from")) : today()->subDays(29);
        $to = $request->filled("to") ? Carbon::parse($request->input("to")) : today();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return view("production.report", ["data" => $report->build($from, $to), "from" => $from, "to" => $to]);
    }

    public function storePhotos(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $request->validate([
            "photos" => "required|array|min:1|max:10",
            "photos.*" => "image|max:5120",
            "caption" => "nullable|string|max:150",
        ]);

        foreach ($request->file("photos") as $photo) {
            $order->photos()->create([
                "path" => $photo->store("order-photos", "public"),
                "caption" => $request->input("caption"),
                "user_id" => $request->user()->id,
            ]);
        }

        return back()->with("success", __("app.photos_added"));
    }

    public function destroyPhoto(Request $request, Order $order, OrderPhoto $photo): RedirectResponse
    {
        abort_unless($photo->order_id === $order->id, 404);
        abort_unless($request->user()->hasRole(["admin", "owner"]), 403);

        Storage::disk("public")->delete($photo->path);
        $photo->delete();

        return back()->with("success", __("app.photo_removed"));
    }

    public function updateIntake(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize("viewAny", Order::class);

        $data = $request->validate([
            "care_flags" => "nullable|array",
            "care_flags.*" => ["string", Rule::in(Order::CARE_FLAGS)],
            "intake_notes" => "nullable|string|max:1000",
        ]);

        $order->update([
            "care_flags" => array_values($data["care_flags"] ?? []) ?: null,
            "intake_notes" => $data["intake_notes"] ?? null,
        ]);

        return back()->with("success", __("app.intake_saved"));
    }

    public static function employeesForSelect()
    {
        return Employee::whereIn("status", ["probation", "active"])->orderBy("name")->get(["id", "name", "user_id"]);
    }

    private function employees()
    {
        return self::employeesForSelect();
    }

    private function back(Request $request, Order $order, string $message, array $warnings = []): RedirectResponse
    {
        $response = $request->input("return") === "order" ? redirect()->route("orders.show", $order) : back();
        $response = $response->with("success", $message);

        return $warnings ? $response->with("warning", implode(" ", $warnings)) : $response;
    }
}
