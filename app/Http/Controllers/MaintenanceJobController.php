<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\MaintenanceJob;
use App\Models\MaintenanceJobItem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MaintenanceJobController extends Controller
{
    public function index(Request $request)
    {
        $query = MaintenanceJob::with("equipment");

        if ($equipmentId = $request->equipment_id) {
            $query->where("equipment_id", $equipmentId);
        }

        if ($kind = $request->kind) {
            $query->where("kind", $kind);
        }

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        $jobs = $query->latest("service_date")->paginate(15)->withQueryString();
        $equipments = Equipment::orderBy("name")->get();

        return view("resources.maintenance.index", compact("jobs", "equipments"));
    }

    public function create()
    {
        return view("resources.maintenance.create", $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $jobData = Arr::except($data, ["register_expense", "expense_category"]);
        $jobData["cost"] = $data["cost"] ?? 0;

        if (!empty($data["register_expense"])) {
            AccountingPeriod::assertOpen($jobData["service_date"], "service_date");
        }

        $job = new MaintenanceJob($jobData);
        $job->completed_at = $job->status === "completed" ? now() : null;
        $job->save();

        $job->syncExpense((bool) ($data["register_expense"] ?? false), $data["expense_category"] ?? "maintenance");

        return redirect()->route("resources.maintenance.show", $job)
            ->with("success", __('app.job_created'));
    }

    public function show(MaintenanceJob $maintenance)
    {
        $maintenance->load(["equipment", "employee", "expense", "parts.item"]);

        $availableParts = InventoryItem::where("kind", "part")
            ->where("is_active", true)
            ->where("stock_qty", ">", 0)
            ->whereNotIn("id", $maintenance->parts->pluck("inventory_item_id"))
            ->orderBy("name")
            ->get();

        return view("resources.maintenance.show", compact("maintenance", "availableParts"));
    }

    public function edit(MaintenanceJob $maintenance)
    {
        return view("resources.maintenance.edit", array_merge(compact("maintenance"), $this->formData()));
    }

    public function update(Request $request, MaintenanceJob $maintenance)
    {
        $data = $this->validated($request, $maintenance);
        $jobData = Arr::except($data, ["register_expense", "expense_category"]);
        $jobData["cost"] = $data["cost"] ?? 0;

        if ($maintenance->status === "cancelled") {
            return redirect()->route("resources.maintenance.show", $maintenance)
                ->with("error", __('app.job_cancelled_locked'));
        }

        if ($maintenance->expense_id || !empty($data["register_expense"])) {
            AccountingPeriod::assertOpen($maintenance->service_date, "service_date");
            AccountingPeriod::assertOpen($jobData["service_date"], "service_date");
        }

        $wasCompleted = $maintenance->status === "completed";
        $maintenance->fill($jobData);
        $maintenance->completed_at = $maintenance->status === "completed"
            ? ($wasCompleted ? $maintenance->completed_at : now())
            : null;
        $maintenance->save();

        $maintenance->syncExpense((bool) ($data["register_expense"] ?? false), $data["expense_category"] ?? "maintenance");

        return redirect()->route("resources.maintenance.show", $maintenance)
            ->with("success", __('app.job_updated'));
    }

    public function destroy(MaintenanceJob $maintenance)
    {
        if ($maintenance->expense_id && $period = AccountingPeriod::closedOn($maintenance->service_date)) {
            return redirect()->route("resources.maintenance.show", $maintenance)
                ->with("error", __("app.period_closed", ["period" => $period->label()]));
        }

        $maintenance->cancel();
        $maintenance->delete();

        return redirect()->route("resources.maintenance.index")
            ->with("success", __('app.job_deleted'));
    }

    public function cancel(MaintenanceJob $maintenance)
    {
        if ($maintenance->status === "cancelled") {
            return back()->with("error", __('app.job_already_cancelled'));
        }

        $maintenance->cancel();

        return redirect()->route("resources.maintenance.show", $maintenance)
            ->with("success", __('app.job_cancelled'));
    }

    public function addPart(Request $request, MaintenanceJob $maintenance)
    {
        $validated = $request->validate([
            "inventory_item_id" => "required|exists:inventory_items,id",
            "quantity" => "required|numeric|min:0.01",
        ]);

        if ($maintenance->status === "cancelled") {
            return back()->with("error", __('app.job_cancelled_locked'));
        }

        $item = InventoryItem::findOrFail($validated["inventory_item_id"]);

        if ((float) $validated["quantity"] > (float) $item->stock_qty) {
            return back()->withInput()->with("error", __('app.movement_insufficient_stock'));
        }

        $maintenance->addPart($item, (float) $validated["quantity"]);

        return back()->with("success", __('app.part_added'));
    }

    public function removePart(MaintenanceJob $maintenance, MaintenanceJobItem $part)
    {
        $maintenance->removePart($part);

        return back()->with("success", __('app.part_removed'));
    }

    private function formData(): array
    {
        return [
            "equipments" => Equipment::orderBy("name")->get(),
            "employees" => Employee::where("status", "!=", "terminated")->orderBy("name")->get(),
            "categories" => ExpenseCategory::orderBy("name")->get(),
        ];
    }

    private function validated(Request $request, ?MaintenanceJob $job = null): array
    {
        return $request->validate([
            "equipment_id" => "required|exists:equipments,id",
            "kind" => "required|in:".implode(",", MaintenanceJob::KINDS),
            "title" => "required|string|max:200",
            "description" => "nullable|string|max:2000",
            "fault_description" => "nullable|string|max:2000",
            "technician" => "nullable|string|max:150",
            "employee_id" => "nullable|exists:employees,id",
            "service_date" => "required|date",
            "cost" => "nullable|numeric|min:0",
            "status" => "required|in:".implode(",", MaintenanceJob::STATUSES),
            "next_service_date" => "nullable|date",
            "register_expense" => "nullable|boolean",
            "expense_category" => "nullable|required_if:register_expense,1|exists:expense_categories,key",
        ]);
    }
}
