<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\MachineLoad;
use App\Models\MaintenanceJob;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\OrderRework;
use App\Models\OrderStageLog;
use App\Models\Service;
use App\Models\ServiceRecipe;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Service $service;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "0");

        $this->admin = User::factory()->create();
        $this->customer = Customer::create(["name" => "Ana", "phone" => "+258841234567", "address" => "Matola"]);
        $this->service = Service::create(["name" => "Lavar", "type" => "by_weight", "price" => 100, "estimated_days" => 2]);
    }

    private function order(string $status = "received", array $overrides = [], float $kg = 5): Order
    {
        $order = Order::create(array_merge([
            "order_number" => "PR-" . str_pad((string) ++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $this->customer->id,
            "status" => $status,
            "received_at" => today()->toDateString(),
            "delivery_date" => today()->addDays(2)->toDateString(),
            "subtotal" => $kg * 100,
            "vat_rate" => 0,
            "vat_amount" => 0,
            "total_price" => $kg * 100,
        ], $overrides));
        $order->items()->create(["service_id" => $this->service->id, "type" => "by_weight", "weight" => $kg, "amount" => 1, "unit_price" => 100, "subtotal" => $kg * 100]);

        return $order;
    }

    private function employee(string $name = "Rosa", ?User $user = null): Employee
    {
        return Employee::create(["name" => $name, "position" => "Lavandaria", "hire_date" => today()->subYear()->toDateString(), "status" => "active", "user_id" => $user?->id]);
    }

    private function machine(string $type = "washer", array $overrides = []): Equipment
    {
        return Equipment::create(array_merge(["name" => $type === "washer" ? "Lavadora 1" : "Secadora 1", "type" => $type, "status" => "operational", "capacity_kg" => 10], $overrides));
    }

    private function item(float $stock = 10): InventoryItem
    {
        $item = InventoryItem::create(["kind" => "product", "name" => "Detergente", "type" => "detergent", "unit" => "l", "unit_cost" => 200]);
        $this->actingAs($this->admin)->post("/resources/items/{$item->id}/movements", [
            "direction" => "in", "quantity" => $stock, "unit_cost" => 200, "reason" => "purchase", "moved_at" => today()->toDateString(),
        ]);

        return $item->fresh();
    }

    // ---------------- Phase 1: visibility & deadlines ----------------

    public function test_every_status_change_is_logged_with_user_and_operator(): void
    {
        $order = $this->order();
        $rosa = $this->employee();

        $this->assertDatabaseHas("order_stage_logs", ["order_id" => $order->id, "from_status" => null, "to_status" => "received"]);

        $this->actingAs($this->admin)->post(route("production.advance", $order), ["employee_id" => $rosa->id])
            ->assertSessionHasNoErrors()->assertSessionHas("success");

        $this->assertSame("washing", $order->fresh()->status);
        $this->assertDatabaseHas("order_stage_logs", [
            "order_id" => $order->id, "from_status" => "received", "to_status" => "washing",
            "user_id" => $this->admin->id, "employee_id" => $rosa->id,
        ]);
        $this->assertNotNull($order->fresh()->stageSince());
    }

    public function test_board_shows_orders_by_stage_and_flags_late_ones(): void
    {
        $late = $this->order("washing", ["delivery_date" => today()->subDay()->toDateString()]);
        $today = $this->order("ironing", ["delivery_date" => today()->toDateString(), "is_express" => true]);
        $ready = $this->order("ready");

        $this->assertSame(1, Order::late()->count());
        $this->assertSame(1, Order::dueToday()->count());
        $this->assertTrue($late->isLate());

        $this->actingAs($this->admin)->get(route("production.board"))
            ->assertOk()
            ->assertSee($late->order_number)
            ->assertSee($today->order_number)
            ->assertDontSee($ready->order_number)
            ->assertSee("is-late", false);

        // The late filter keeps only late / due-today orders.
        $this->order("received", ["delivery_date" => today()->addDays(5)->toDateString(), "order_number" => "PR-FUTURE"]);
        $this->actingAs($this->admin)->get(route("production.board", ["late" => 1]))
            ->assertSee($late->order_number)->assertDontSee("PR-FUTURE");
    }

    public function test_cashier_can_use_the_board_but_not_the_report(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->openTill($cashier);
        $order = $this->order();

        $this->actingAs($cashier)->get(route("production.board"))->assertOk();
        $this->actingAs($cashier)->post(route("production.advance", $order))->assertSessionHasNoErrors();
        $this->assertSame("washing", $order->fresh()->status);
        $this->actingAs($cashier)->get(route("production.report"))->assertForbidden();
    }

    public function test_reschedule_keeps_the_first_promise_and_notifies_the_customer(): void
    {
        $order = $this->order("washing", ["delivery_date" => today()->subDay()->toDateString()]);
        $template = MessageTemplate::where("name", "Encomenda atrasada")->first();
        $this->assertNotNull($template, "migration seeds the delay template");
        SiteSetting::set("notify_order_delayed_enabled", "1");
        SiteSetting::set("notify_order_delayed_template_id", $template->id);

        $this->actingAs($this->admin)->post(route("production.reschedule", $order), [
            "delivery_date" => today()->addDay()->toDateString(),
            "reason" => "Avaria na secadora",
            "notify" => 1,
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(today()->subDay()->toDateString(), $order->original_delivery_date->toDateString());
        $this->assertSame(today()->addDay()->toDateString(), $order->delivery_date->toDateString());
        $this->assertSame("Avaria na secadora", $order->delay_reason);
        $this->assertFalse($order->isLate());
        $this->assertDatabaseHas("whatsapp_messages", ["template_id" => $template->id]);

        // A second new date does not overwrite the original promise.
        $this->actingAs($this->admin)->post(route("production.reschedule", $order), [
            "delivery_date" => today()->addDays(3)->toDateString(), "reason" => "Mais atraso",
        ])->assertSessionHasNoErrors();
        $this->assertSame(today()->subDay()->toDateString(), $order->fresh()->original_delivery_date->toDateString());
    }

    public function test_report_measures_output_punctuality_and_stage_times(): void
    {
        $onTime = $this->order("folding", ["delivery_date" => today()->toDateString()], 4);
        $late = $this->order("folding", ["delivery_date" => today()->subDays(2)->toDateString(), "received_at" => today()->subDays(4)->toDateString()], 6);
        $this->actingAs($this->admin)->post(route("production.advance", $onTime));
        $this->actingAs($this->admin)->post(route("production.advance", $late));
        Complaint::create(["number" => "RC-1", "customer_id" => $this->customer->id, "order_id" => $late->id, "channel" => "counter", "category" => "stain", "stage" => "washing", "description" => "x", "status" => "open", "due_at" => today()->addDays(3), "opened_by" => $this->admin->id]);

        $this->actingAs($this->admin)->get(route("production.report"))
            ->assertOk()
            ->assertViewHas("data", function (array $data) {
                return $data["output"]["kg"] == 10
                    && $data["output"]["orders"] === 2
                    && $data["punctuality"]["measured"] === 2
                    && $data["punctuality"]["on_time"] === 1
                    && $data["punctuality"]["rate"] == 50.0
                    && $data["quality"]["complaints"] === 1
                    && $data["quality"]["by_stage"] === ["washing" => 1]
                    && $data["stage_times"]["stages"]["folding"]["count"] === 2;
            });
    }

    // ---------------- Phase 2: machine loads, capacity, cycles ----------------

    public function test_machine_load_moves_orders_counts_cycles_consumes_recipe_and_schedules_service(): void
    {
        $washer = $this->machine("washer", ["service_interval_cycles" => 2]);
        $item = $this->item(10);
        $washer->recipes()->create(["inventory_item_id" => $item->id, "quantity" => 0.12]);
        $rosa = $this->employee();
        $a = $this->order("received", [], 4);
        $b = $this->order("received", [], 5);

        $this->actingAs($this->admin)->post(route("production.loads.store"), [
            "stage" => "washing", "equipment_id" => $washer->id, "program" => "Algodão 40°", "employee_id" => $rosa->id,
            "orders" => [$a->id => 4, $b->id => 5],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $load = MachineLoad::firstOrFail();
        $this->assertSame("in_progress", $load->status);
        $this->assertSame("9.00", (string) $load->weight_kg);
        $this->assertSame(90.0, $load->utilization());
        $this->assertSame("washing", $a->fresh()->status);
        $this->assertDatabaseHas("order_stage_logs", ["order_id" => $a->id, "to_status" => "washing", "employee_id" => $rosa->id]);

        // The machine is busy until the cycle ends.
        $c = $this->order("received", [], 2);
        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "orders" => [$c->id => 2]])
            ->assertSessionHasErrors("equipment_id");

        $this->actingAs($this->admin)->post(route("production.loads.finish", $load))->assertSessionHasNoErrors();
        $this->assertSame(1, $washer->fresh()->cycles_count);
        $this->assertSame("9.88", (string) $item->fresh()->stock_qty);
        $this->assertDatabaseHas("inventory_movements", ["source_type" => "machine_load", "source_id" => $load->id, "reason" => "consumption"]);
        $this->assertSame(0, MaintenanceJob::count());

        // Second cycle reaches the interval: one preventive service is opened.
        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "orders" => [$c->id => 2]]);
        $this->actingAs($this->admin)->post(route("production.loads.finish", MachineLoad::latest("id")->first()))->assertSessionHas("warning");
        $job = MaintenanceJob::firstOrFail();
        $this->assertSame("preventive", $job->kind);
        $this->assertSame("scheduled", $job->status);
        $this->assertTrue($washer->fresh()->serviceDue());

        // Completing the service restarts the cycle counter.
        $job->update(["status" => "completed", "completed_at" => now()]);
        $this->assertSame(0, $washer->fresh()->cyclesSinceService());
        $this->assertFalse($washer->fresh()->serviceDue());
    }

    public function test_overloaded_machine_needs_a_justification_and_dryer_needs_dryer(): void
    {
        $washer = $this->machine("washer", ["capacity_kg" => 8]);
        $order = $this->order("received", [], 10);

        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "orders" => [$order->id => 10]])
            ->assertSessionHasErrors("overload_note");
        $this->assertSame(0, MachineLoad::count());

        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "drying", "equipment_id" => $washer->id, "orders" => [$order->id => 10], "overload_note" => "x"])
            ->assertSessionHasErrors("equipment_id");

        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "orders" => [$order->id => 10], "overload_note" => "Edredão grande"])
            ->assertSessionHasNoErrors();
        $this->assertSame("Edredão grande", MachineLoad::first()->overload_note);

        // An order already past drying cannot go into a wash load.
        $ironing = $this->order("ironing");
        $washer2 = $this->machine("washer", ["name" => "Lavadora 2"]);
        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer2->id, "orders" => [$ironing->id => 3]])
            ->assertSessionHasErrors("orders");
    }

    public function test_capacity_pushes_the_promise_date_back(): void
    {
        $this->order("washing", [], 40);   // 40 kg already in the queue

        // No capacity configured: the service lead time decides.
        $this->actingAs($this->admin)->post(route("orders.store"), [
            "customer_id" => $this->customer->id, "received_at" => today()->toDateString(),
            "items" => [["service_id" => $this->service->id, "weight" => 10]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(today()->addDays(2)->toDateString(), Order::latest("id")->first()->delivery_date->toDateString());

        // 30 kg/day: 40 + 10 kg need 2 days of work -> still 2 days; 20 kg/day -> 3 days.
        SiteSetting::set("production_capacity_kg", "20");
        $this->actingAs($this->admin)->post(route("orders.store"), [
            "customer_id" => $this->customer->id, "received_at" => today()->toDateString(),
            "items" => [["service_id" => $this->service->id, "weight" => 10]],
        ])->assertSessionHasNoErrors();
        // Queue is now 40 + 10 (previous order) = 50 kg; + 10 = 60 kg / 20 = 3 days.
        $this->assertSame(today()->addDays(3)->toDateString(), Order::latest("id")->first()->delivery_date->toDateString());

        $this->actingAs($this->admin)->getJson(route("production.promise", ["kg" => 10, "lead_days" => 1, "wanted" => today()->addDay()->toDateString()]))
            ->assertOk()->assertJson(["enabled" => true, "fits" => false, "days_needed" => 4]);
    }

    // ---------------- Phase 3: quality, rework, complaints, costs ----------------

    public function test_intake_inspection_care_flags_notes_and_photos(): void
    {
        Storage::fake("public");

        $this->actingAs($this->admin)->post(route("orders.store"), [
            "customer_id" => $this->customer->id, "received_at" => today()->toDateString(),
            "items" => [["service_id" => $this->service->id, "weight" => 3]],
            "care_flags" => ["delicate", "separate_colors"],
            "intake_notes" => "Mancha na gola",
            "photos" => [UploadedFile::fake()->image("gola.jpg")],
        ])->assertSessionHasNoErrors();

        $order = Order::latest("id")->first();
        $this->assertSame(["delicate", "separate_colors"], $order->care_flags);
        $this->assertSame("Mancha na gola", $order->intake_notes);
        $this->assertCount(1, $order->photos);
        Storage::disk("public")->assertExists($order->photos->first()->path);

        $this->actingAs($this->admin)->get(route("orders.show", $order))->assertOk()->assertSee("Mancha na gola");
        $this->actingAs($this->admin)->get(route("production.board"))->assertSee(__("app.care_delicate"));

        $this->actingAs($this->admin)->put(route("production.intake", $order), ["care_flags" => ["hand_wash"], "intake_notes" => ""])->assertSessionHasNoErrors();
        $this->assertSame(["hand_wash"], $order->fresh()->care_flags);

        $this->actingAs($this->admin)->post(route("orders.store"), [
            "customer_id" => $this->customer->id, "received_at" => today()->toDateString(),
            "items" => [["service_id" => $this->service->id, "weight" => 3]],
            "care_flags" => ["invented"],
        ])->assertSessionHasErrors("care_flags.0");
    }

    public function test_quality_check_gate_pass_and_fail_with_rework(): void
    {
        SiteSetting::set("qc_required", "1");
        $item = $this->item(10);
        ServiceRecipe::create(["service_id" => $this->service->id, "inventory_item_id" => $item->id, "quantity" => 0.1, "basis" => "per_kg", "stage" => "washing"]);
        $order = $this->order("received", [], 5);

        foreach (["washing", "drying", "ironing", "folding"] as $stage) {
            $this->actingAs($this->admin)->post(route("production.advance", $order))->assertSessionHasNoErrors();
            $this->assertSame($stage, $order->fresh()->status);
        }
        $this->assertSame("9.50", (string) $item->fresh()->stock_qty);

        // Nothing skips the quality check: neither the board nor the order form.
        $this->actingAs($this->admin)->post(route("production.advance", $order))->assertSessionHasErrors("status");
        $this->actingAs($this->admin)->put(route("orders.update", $order), [
            "customer_id" => $order->customer_id, "received_at" => $order->received_at->toDateString(), "status" => "ready",
        ]);
        $this->assertSame("folding", $order->fresh()->status);

        // Failed: back to washing, the wash is consumed again, one rework recorded.
        $this->actingAs($this->admin)->post(route("production.qc", $order), ["result" => "fail", "reason" => "Mancha não saiu"])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame("washing", $order->status);
        $this->assertSame(1, $order->rework_count);
        $this->assertSame("9.00", (string) $item->fresh()->stock_qty);
        $this->assertDatabaseHas("order_reworks", ["order_id" => $order->id, "source" => "qc", "from_status" => "folding", "reason" => "Mancha não saiu"]);
        $this->assertDatabaseHas("order_stage_logs", ["order_id" => $order->id, "from_status" => "folding", "to_status" => "washing", "note" => "rework"]);

        foreach (["drying", "ironing", "folding"] as $stage) {
            $this->actingAs($this->admin)->post(route("production.advance", $order));
        }
        $this->actingAs($this->admin)->post(route("production.qc", $order), ["result" => "pass"])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame("ready", $order->status);
        $this->assertNotNull($order->qc_passed_at);
        $this->assertSame($this->admin->id, $order->qc_by);
        $this->assertSame("9.00", (string) $item->fresh()->stock_qty, "drying/ironing were not consumed twice beyond the recipe");
    }

    public function test_complaint_is_attributed_to_load_and_operator_and_creates_a_free_rework_order(): void
    {
        $washer = $this->machine();
        $rosa = $this->employee();
        $order = $this->order("received", [], 4);
        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "employee_id" => $rosa->id, "orders" => [$order->id => 4]]);
        $load = MachineLoad::firstOrFail();
        $this->actingAs($this->admin)->post(route("production.loads.finish", $load));
        $order->update(["status" => "completed", "completed_at" => now()]);

        $this->actingAs($this->admin)->post(route("complaints.store"), [
            "order_id" => $order->id, "channel" => "counter", "category" => "stain", "description" => "Mancha de gordura",
        ])->assertSessionHasNoErrors();
        $complaint = Complaint::firstOrFail();
        $this->assertSame("washing", $complaint->stage);
        $this->assertSame($load->id, $complaint->machine_load_id);
        $this->assertSame($rosa->id, $complaint->employee_id);

        $this->actingAs($this->admin)->get(route("complaints.show", $complaint))->assertOk()->assertSee($load->code);

        $this->actingAs($this->admin)->post(route("complaints.rework", $complaint))->assertSessionHasNoErrors()->assertRedirect();
        $rework = Order::where("rework_of_order_id", $order->id)->firstOrFail();
        $this->assertSame(0.0, (float) $rework->total_price);
        $this->assertSame("received", $rework->status);
        $this->assertSame(4.0, $rework->weightKg());
        $this->assertSame(1, $order->fresh()->rework_count);
        $this->assertDatabaseHas("order_reworks", ["order_id" => $order->id, "source" => "complaint", "complaint_id" => $complaint->id]);

        // Only one open re-wash order per original order.
        $this->actingAs($this->admin)->post(route("complaints.rework", $complaint))->assertSessionHasErrors("order");

        $this->actingAs($this->admin)->get(route("production.report"))->assertViewHas("data", fn ($d) => $d["quality"]["by_machine"] === ["Lavadora 1" => 1] && $d["quality"]["by_employee"] === ["Rosa" => 1]);
    }

    public function test_report_productivity_costs_and_consumption_variance(): void
    {
        $washer = $this->machine();
        $rosa = $this->employee();
        $item = $this->item(10);
        ServiceRecipe::create(["service_id" => $this->service->id, "inventory_item_id" => $item->id, "quantity" => 0.1, "basis" => "per_kg", "stage" => "washing"]);
        Attendance::create(["employee_id" => $rosa->id, "attendance_date" => today()->toDateString(), "status" => "present"]);

        $order = $this->order("received", [], 6);
        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "employee_id" => $rosa->id, "orders" => [$order->id => 6]]);
        $this->actingAs($this->admin)->post(route("production.loads.finish", MachineLoad::first()));
        foreach (["drying", "ironing", "folding", "ready"] as $stage) {
            $this->actingAs($this->admin)->post(route("production.advance", $order), ["employee_id" => $rosa->id]);
        }
        $this->assertSame("ready", $order->fresh()->status);

        // Stock count found 0.2 l more missing than the recipes say.
        $this->actingAs($this->admin)->post("/resources/items/{$item->id}/movements", [
            "direction" => "out", "quantity" => 0.2, "reason" => "adjustment", "moved_at" => today()->toDateString(),
        ]);

        $this->actingAs($this->admin)->get(route("production.report"))->assertOk()->assertViewHas("data", function (array $d) use ($rosa, $item) {
            $op = collect($d["operators"])->firstWhere("employee.id", $rosa->id);
            $mat = $d["materials"]["items"]->firstWhere("item.id", $item->id);
            $service = $d["materials"]["services"]->firstWhere("service.id", $this->service->id);

            return $op["loads"] === 1 && $op["kg_loaded"] == 6.0 && $op["days_present"] === 1 && $op["kg_per_day"] == 6.0
                && $op["finished"]["drying"]["orders"] === 1
                && $mat["consumed"] == 0.6 && $mat["adjusted"] == 0.2 && $mat["variance"] == 33.3
                && $d["materials"]["total_cost"] == 160 && $d["materials"]["cost_per_kg"] == 26.7
                && $service["unit_cost"] == 20.0 && $service["margin"] == 80.0
                && $d["machines"]["total_loads"] === 1;
        });
    }

    public function test_manual_rework_from_board_and_order_paid_early_stays_in_production(): void
    {
        $order = $this->order("ironing");
        $this->actingAs($this->admin)->post(route("production.rework", $order), ["reason" => "Mal passada"])->assertSessionHasNoErrors();
        $this->assertSame("washing", $order->fresh()->status);
        $this->assertSame("manual", OrderRework::first()->source);

        $received = $this->order("received");
        $this->actingAs($this->admin)->post(route("production.rework", $received), ["reason" => "x"])->assertSessionHasErrors("status");

        $this->assertSame(2, OrderStageLog::where("order_id", $order->id)->count());
    }

    public function test_production_pages_render(): void
    {
        $washer = $this->machine("washer", ["service_interval_cycles" => 100]);
        $this->machine("dryer");
        $order = $this->order("received", ["care_flags" => ["delicate"]]);
        $this->actingAs($this->admin)->post(route("production.loads.store"), ["stage" => "washing", "equipment_id" => $washer->id, "orders" => [$order->id => 5]]);
        $load = MachineLoad::firstOrFail();

        foreach ([
            route("production.loads.index"),
            route("production.loads.create", ["stage" => "washing"]),
            route("production.loads.create", ["stage" => "drying", "orders" => [$order->id]]),
            route("production.loads.show", $load),
            route("resources.equipments.show", $washer),
            route("resources.equipments.edit", $washer),
            route("admin.business-settings.index"),
            route("orders.create"),
            route("complaints.create", ["order" => $order->id]),
            route("orders.show", $order),
            route("production.report", ["from" => today()->subDays(3)->toDateString(), "to" => today()->toDateString()]),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }
}
