<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\MachineLoad;
use App\Models\MaintenanceJob;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Machine cycles. Starting a load moves its orders to the load's stage (washing or
 * drying) through the normal flow; finishing it counts a cycle on the machine,
 * consumes the machine's per-cycle recipe and opens a preventive service when the
 * machine reaches its cycle interval.
 */
class MachineLoads
{
    public function __construct(private Production $production) {}

    /**
     * @param  array<int, float>  $orders  order id => kg in this load
     */
    public function start(Equipment $machine, string $stage, array $orders, User $user, array $data = []): MachineLoad
    {
        if (!in_array($stage, MachineLoad::STAGES, true)) {
            throw ValidationException::withMessages(["stage" => __("validation.in", ["attribute" => "stage"])]);
        }

        if ($machine->status !== "operational") {
            throw ValidationException::withMessages(["equipment_id" => __("app.machine_not_operational")]);
        }

        if ($machine->type !== MachineLoad::MACHINE_TYPES[$stage]) {
            throw ValidationException::withMessages(["equipment_id" => __("app.machine_wrong_type")]);
        }

        if (MachineLoad::inProgress()->where("equipment_id", $machine->id)->exists()) {
            throw ValidationException::withMessages(["equipment_id" => __("app.machine_busy")]);
        }

        $orders = array_filter($orders, fn ($kg) => (float) $kg > 0);
        if ($orders === []) {
            throw ValidationException::withMessages(["orders" => __("app.load_needs_orders")]);
        }

        $models = Order::whereIn("id", array_keys($orders))->get()->keyBy("id");
        $maxRank = array_search($stage, Order::STATUSES, true);
        foreach ($models as $order) {
            if ($order->abandoned_at || $order->statusRank() > $maxRank) {
                throw ValidationException::withMessages(["orders" => __("app.load_order_wrong_stage", ["order" => $order->order_number])]);
            }
        }

        $total = round(array_sum(array_map("floatval", $orders)), 2);
        $capacity = (float) ($machine->capacity_kg ?? 0);
        if ($capacity > 0 && $total > $capacity && blank($data["overload_note"] ?? null)) {
            throw ValidationException::withMessages(["overload_note" => __("app.load_over_capacity", [
                "kg" => $total, "capacity" => rtrim(rtrim(number_format($capacity, 2, ".", ""), "0"), "."),
            ])]);
        }

        $load = DB::transaction(function () use ($machine, $stage, $orders, $user, $data, $total) {
            $load = MachineLoad::create([
                "code" => MachineLoad::nextCode(),
                "equipment_id" => $machine->id,
                "stage" => $stage,
                "program" => $data["program"] ?? null,
                "weight_kg" => $total,
                "employee_id" => $data["employee_id"] ?? null,
                "user_id" => $user->id,
                "status" => "in_progress",
                "started_at" => now(),
                "overload_note" => $data["overload_note"] ?? null,
                "notes" => $data["notes"] ?? null,
            ]);

            foreach ($orders as $orderId => $kg) {
                $load->orders()->attach($orderId, ["weight_kg" => round((float) $kg, 2)]);
            }

            return $load;
        });

        // Orders behind the load's stage reach it now (stock consumption, history, operator).
        foreach ($models as $order) {
            if ($order->statusRank() < $maxRank) {
                $this->production->advance($order, $user, $data["employee_id"] ?? null, $stage);
            }
        }

        return $load;
    }

    /** @return array{maintenance: ?MaintenanceJob, shortfalls: array<int, float>} */
    public function finish(MachineLoad $load, User $user): array
    {
        if ($load->status !== "in_progress") {
            throw ValidationException::withMessages(["status" => __("app.load_not_in_progress")]);
        }

        return DB::transaction(function () use ($load, $user) {
            $load->update(["status" => "done", "finished_at" => now()]);

            $machine = $load->equipment;
            $shortfalls = [];
            $maintenance = null;

            if ($machine) {
                $machine->increment("cycles_count");

                foreach ($machine->recipes()->with("item")->get() as $recipe) {
                    $item = $recipe->item;
                    if (!$item instanceof InventoryItem || !$item->is_active) {
                        continue;
                    }

                    $result = InventoryMovement::consume($item, (float) $recipe->quantity, [
                        "direction" => "out",
                        "unit_cost" => null,
                        "reason" => "consumption",
                        "user_id" => $user->id,
                        "source_type" => "machine_load",
                        "source_id" => $load->id,
                        "moved_at" => today()->toDateString(),
                        "notes" => __("app.load_consumption_note", ["code" => $load->code, "machine" => $machine->name]),
                    ]);

                    if ($result["shortfall"] > 0) {
                        $shortfalls[$item->id] = $result["shortfall"];
                    }
                }

                $maintenance = $this->scheduleServiceIfDue($machine->fresh());
            }

            return ["maintenance" => $maintenance, "shortfalls" => $shortfalls];
        });
    }

    public function cancel(MachineLoad $load): void
    {
        if ($load->status !== "in_progress") {
            throw ValidationException::withMessages(["status" => __("app.load_not_in_progress")]);
        }

        // The orders keep their stage: the clothes may already be wet, and stages never go back.
        $load->update(["status" => "cancelled", "finished_at" => now()]);
    }

    /** Opens one preventive service when the machine reaches its cycle interval. */
    private function scheduleServiceIfDue(Equipment $machine): ?MaintenanceJob
    {
        if (!$machine->serviceDue()) {
            return null;
        }

        $open = MaintenanceJob::where("equipment_id", $machine->id)->where("kind", "preventive")
            ->whereIn("status", ["scheduled", "in_progress"])->exists();

        if ($open) {
            return null;
        }

        return MaintenanceJob::create([
            "equipment_id" => $machine->id,
            "kind" => "preventive",
            "title" => __("app.service_by_cycles_title", ["cycles" => $machine->cyclesSinceService()]),
            "description" => __("app.service_by_cycles_desc", ["interval" => $machine->service_interval_cycles]),
            "service_date" => today()->toDateString(),
            "status" => "scheduled",
        ]);
    }
}
