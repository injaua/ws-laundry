<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\MachineLoad;
use App\Models\Order;
use App\Models\OrderRework;
use App\Models\OrderStageLog;
use App\Models\Service;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Production indicators for a period: output, flow times, punctuality, quality, machines, people, materials. */
class ProductionReport
{
    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();

        $readyOrders = Order::with("items")->whereBetween("ready_at", [$from, $to])->get();
        $output = $this->output($readyOrders, $from, $to);

        return [
            "output" => $output,
            "stage_times" => $this->stageTimes($from, $to),
            "punctuality" => $this->punctuality($readyOrders),
            "quality" => $this->quality($readyOrders, $from, $to),
            "machines" => $this->machines($from, $to),
            "operators" => $this->operators($from, $to),
            "materials" => $this->materials($from, $to, $output["kg"]),
        ];
    }

    /** Kilos, pieces and orders that reached "ready", per day. */
    private function output(Collection $orders, CarbonInterface $from, CarbonInterface $to): array
    {
        $days = [];
        foreach ($orders->groupBy(fn (Order $o) => $o->ready_at->toDateString()) as $day => $group) {
            $days[$day] = [
                "orders" => $group->count(),
                "kg" => round($group->sum(fn (Order $o) => $o->weightKg()), 2),
                "pieces" => (int) $group->sum(fn (Order $o) => $o->pieceCount()),
            ];
        }
        ksort($days);

        $workingDays = max(1, count($days));

        return [
            "days" => $days,
            "orders" => $orders->count(),
            "kg" => round($orders->sum(fn (Order $o) => $o->weightKg()), 2),
            "pieces" => (int) $orders->sum(fn (Order $o) => $o->pieceCount()),
            "kg_per_day" => round($orders->sum(fn (Order $o) => $o->weightKg()) / $workingDays, 1),
            "in_production" => Order::inProduction()->count(),
            "late_now" => Order::late()->count(),
        ];
    }

    /**
     * Stage changes of the current store's orders. order_stage_logs has no store_id (it
     * belongs to an order), so it is filtered through the store-scoped orders, deleted
     * ones included as before.
     */
    private static function storeStageLogs(): \Illuminate\Database\Eloquent\Builder
    {
        return OrderStageLog::query()->whereIn("order_id", Order::withTrashed()->select("id"));
    }

    /** Average hours an order spends in each stage (time until the next stage change). */
    private function stageTimes(CarbonInterface $from, CarbonInterface $to): array
    {
        $orderIds = self::storeStageLogs()->whereBetween("created_at", [$from, $to])->distinct()->pluck("order_id");
        $hours = [];

        OrderStageLog::whereIn("order_id", $orderIds)->orderBy("order_id")->orderBy("created_at")->orderBy("id")
            ->get(["order_id", "to_status", "created_at", "note"])
            ->groupBy("order_id")
            ->each(function (Collection $logs) use (&$hours, $from, $to) {
                $logs = $logs->values();
                for ($i = 0; $i < $logs->count() - 1; $i++) {
                    $log = $logs[$i];
                    if ($log->note === "initial" || !in_array($log->to_status, [...Order::PRODUCTION_STAGES, "ready"], true)
                        || $log->created_at->lt($from) || $log->created_at->gt($to)) {
                        continue;
                    }
                    $hours[$log->to_status][] = $log->created_at->diffInMinutes($logs[$i + 1]->created_at) / 60;
                }
            });

        $result = [];
        foreach ([...Order::PRODUCTION_STAGES, "ready"] as $stage) {
            $values = $hours[$stage] ?? [];
            $result[$stage] = [
                "avg_hours" => $values ? round(array_sum($values) / count($values), 1) : null,
                "count" => count($values),
            ];
        }

        // The slowest production stage is the bottleneck ("ready" is waiting for the customer).
        $averages = array_filter(
            array_map(fn ($r) => $r["avg_hours"], array_intersect_key($result, array_flip(Order::PRODUCTION_STAGES))),
            fn ($hours) => $hours !== null,
        );

        return [
            "stages" => $result,
            "bottleneck" => $averages ? array_search(max($averages), $averages, true) : null,
        ];
    }

    /** Ready by the first promised date (a new date does not hide a delay). */
    private function punctuality(Collection $orders): array
    {
        $withPromise = $orders->filter(fn (Order $o) => ($o->original_delivery_date ?? $o->delivery_date) !== null);
        $onTime = $withPromise->filter(fn (Order $o) => $o->ready_at->toDateString() <= ($o->original_delivery_date ?? $o->delivery_date)->toDateString());

        return [
            "measured" => $withPromise->count(),
            "on_time" => $onTime->count(),
            "rate" => $withPromise->count() ? round($onTime->count() / $withPromise->count() * 100, 1) : null,
            "rescheduled" => $orders->whereNotNull("original_delivery_date")->count(),
        ];
    }

    private function quality(Collection $readyOrders, CarbonInterface $from, CarbonInterface $to): array
    {
        $received = Order::whereDate("received_at", ">=", $from->toDateString())->whereDate("received_at", "<=", $to->toDateString())->count();
        $complaints = Complaint::with(["machineLoad.equipment", "employee"])->whereBetween("created_at", [$from, $to])->get();
        $reworks = OrderRework::whereBetween("created_at", [$from, $to])->get();

        return [
            "orders_received" => $received,
            "complaints" => $complaints->count(),
            "complaints_per_100" => $received ? round($complaints->count() / $received * 100, 1) : null,
            "by_category" => $complaints->countBy("category")->sortDesc()->all(),
            "by_stage" => $complaints->whereNotNull("stage")->countBy("stage")->sortDesc()->all(),
            "by_machine" => $complaints->filter(fn ($c) => $c->machineLoad?->equipment)->countBy(fn ($c) => $c->machineLoad->equipment->name)->sortDesc()->all(),
            "by_employee" => $complaints->filter(fn ($c) => $c->employee)->countBy(fn ($c) => $c->employee->name)->sortDesc()->all(),
            "reworks" => $reworks->count(),
            "rework_rate" => $readyOrders->count() ? round($reworks->count() / $readyOrders->count() * 100, 1) : null,
            "reworks_by_source" => $reworks->countBy("source")->all(),
            "qc_checked" => $readyOrders->whereNotNull("qc_passed_at")->count(),
            "qc_rate" => $readyOrders->count() ? round($readyOrders->whereNotNull("qc_passed_at")->count() / $readyOrders->count() * 100, 1) : null,
        ];
    }

    private function machines(CarbonInterface $from, CarbonInterface $to): array
    {
        $loads = MachineLoad::with("equipment")->where("status", "done")->whereBetween("finished_at", [$from, $to])->get();

        $rows = $loads->groupBy("equipment_id")->map(function (Collection $group) {
            $machine = $group->first()->equipment;
            $utilizations = $group->map(fn (MachineLoad $l) => $l->utilization())->filter(fn ($u) => $u !== null);
            $durations = $group->map(fn (MachineLoad $l) => $l->durationMinutes())->filter(fn ($d) => $d !== null);

            return [
                "machine" => $machine,
                "loads" => $group->count(),
                "kg" => round($group->sum("weight_kg"), 2),
                "avg_kg" => round($group->avg("weight_kg"), 1),
                "utilization" => $utilizations->isNotEmpty() ? round($utilizations->avg(), 1) : null,
                "avg_minutes" => $durations->isNotEmpty() ? (int) round($durations->avg()) : null,
            ];
        })->values();

        $fleet = Equipment::whereIn("type", ["washer", "dryer"])->orderBy("type")->orderBy("name")->get()
            ->map(fn (Equipment $e) => [
                "machine" => $e,
                "cycles" => (int) $e->cycles_count,
                "since_service" => $e->cyclesSinceService(),
                "interval" => $e->service_interval_cycles,
                "due" => $e->serviceDue(),
            ]);

        return ["loads" => $rows, "fleet" => $fleet, "total_loads" => $loads->count()];
    }

    /**
     * Work per employee: kilos loaded in machines and stage changes recorded on the
     * board, set against the days they were present.
     */
    private function operators(CarbonInterface $from, CarbonInterface $to): array
    {
        $loads = MachineLoad::where("status", "done")->whereBetween("finished_at", [$from, $to])->whereNotNull("employee_id")
            ->get(["employee_id", "weight_kg", "stage"]);
        $logs = self::storeStageLogs()->with("order.items")->whereBetween("created_at", [$from, $to])->whereNotNull("employee_id")
            ->where(fn ($q) => $q->whereNull("note")->orWhere("note", "!=", "rework"))
            ->get(["id", "order_id", "employee_id", "from_status", "to_status"]);
        $presence = Attendance::whereDate("attendance_date", ">=", $from->toDateString())->whereDate("attendance_date", "<=", $to->toDateString())
            ->whereIn("status", ["present", "late"])->get(["employee_id"])->countBy("employee_id");

        $ids = $loads->pluck("employee_id")->merge($logs->pluck("employee_id"))->unique();
        $employees = Employee::whereIn("id", $ids)->get()->keyBy("id");

        return $ids->map(function ($id) use ($loads, $logs, $presence, $employees) {
            $mine = $loads->where("employee_id", $id);
            $moves = $logs->where("employee_id", $id);
            // The stage an employee finished is the one the order left.
            $finished = $moves->groupBy("from_status")->map(fn ($group) => [
                "orders" => $group->count(),
                "kg" => round($group->sum(fn ($log) => $log->order?->weightKg() ?? 0), 1),
                "pieces" => (int) $group->sum(fn ($log) => $log->order?->pieceCount() ?? 0),
            ])->all();
            $days = (int) ($presence[$id] ?? 0);
            $kg = round((float) $mine->sum("weight_kg"), 1);

            return [
                "employee" => $employees[$id] ?? null,
                "loads" => $mine->count(),
                "kg_loaded" => $kg,
                "finished" => $finished,
                "pieces_finished" => (int) collect($finished)->only(["ironing", "folding"])->sum("pieces"),
                "days_present" => $days,
                "kg_per_day" => $days ? round($kg / $days, 1) : null,
            ];
        })->sortByDesc("kg_loaded")->values()->all();
    }

    /**
     * Material cost per kilo and theoretical (recipes) vs physical (count adjustments)
     * consumption; and the recipe cost of each service against its price.
     */
    private function materials(CarbonInterface $from, CarbonInterface $to, float $kgProcessed): array
    {
        $movements = InventoryMovement::whereDate("moved_at", ">=", $from->toDateString())->whereDate("moved_at", "<=", $to->toDateString())
            ->whereIn("reason", ["consumption", "adjustment"])->get(["inventory_item_id", "direction", "quantity", "reason"]);
        $items = InventoryItem::whereIn("id", $movements->pluck("inventory_item_id")->unique())->get()->keyBy("id");

        $rows = $movements->groupBy("inventory_item_id")->map(function (Collection $group, $itemId) use ($items) {
            $item = $items[$itemId] ?? null;
            $consumed = (float) $group->where("reason", "consumption")->where("direction", "out")->sum("quantity");
            // Count adjustments: stock found missing (out) means more was used than the recipes say.
            $adjusted = (float) $group->where("reason", "adjustment")->sum(fn ($m) => $m->direction === "out" ? (float) $m->quantity : -(float) $m->quantity);
            $cost = (float) ($item->unit_cost ?? 0);

            return [
                "item" => $item,
                "consumed" => round($consumed, 2),
                "adjusted" => round($adjusted, 2),
                "real" => round($consumed + $adjusted, 2),
                "variance" => $consumed > 0 ? round($adjusted / $consumed * 100, 1) : null,
                "cost" => round(($consumed + max(0, $adjusted)) * $cost),
            ];
        })->sortByDesc("cost")->values();

        $totalCost = (float) $rows->sum("cost");

        $services = Service::with("recipes.item")->orderBy("name")->get()->map(function (Service $service) {
            $basis = $service->type === "by_weight" ? "per_kg" : "per_piece";
            $unitCost = (float) $service->recipes->where("basis", $basis)
                ->sum(fn ($r) => (float) $r->quantity * (float) ($r->item->unit_cost ?? 0));
            $price = (float) $service->price;

            return [
                "service" => $service,
                "unit_cost" => round($unitCost, 2),
                "price" => $price,
                "margin" => $price > 0 ? round(($price - $unitCost) / $price * 100, 1) : null,
                "has_recipe" => $service->recipes->isNotEmpty(),
            ];
        });

        return [
            "items" => $rows,
            "total_cost" => Money::round($totalCost),
            "cost_per_kg" => $kgProcessed > 0 ? round($totalCost / $kgProcessed, 1) : null,
            "services" => $services,
        ];
    }
}
