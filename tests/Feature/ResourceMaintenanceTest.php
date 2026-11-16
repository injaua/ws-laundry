<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Equipment;
use App\Models\Expense;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function equipment(): Equipment
    {
        return Equipment::create(["name" => "Máquina de lavar 10kg", "type" => "washer", "status" => "operational"]);
    }

    private function part(float $stock = 10): InventoryItem
    {
        return InventoryItem::create([
            "kind" => "part",
            "name" => "Correia B-52",
            "type" => "belt",
            "unit" => "un",
            "stock_qty" => $stock,
        ]);
    }

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            "kind" => "corrective",
            "title" => "Troca de correia",
            "service_date" => now()->toDateString(),
            "status" => "completed",
            "cost" => 5000,
            "register_expense" => 1,
            "expense_category" => "manutencao",
        ], $overrides);
    }

    public function test_cashier_cannot_access_maintenance(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/resources/maintenance")->assertForbidden();
        $this->actingAs($cashier)->post("/resources/maintenance", $this->jobPayload(["equipment_id" => 1]))->assertForbidden();
    }

    public function test_job_cost_creates_linked_expense_and_journal(): void
    {
        $equipment = $this->equipment();

        $this->actingAs($this->admin())->post("/resources/maintenance", $this->jobPayload(["equipment_id" => $equipment->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $job = MaintenanceJob::first();
        $this->assertNotNull($job->completed_at);

        $expense = Expense::firstWhere("source_type", "maintenance_job");
        $this->assertNotNull($expense);
        $this->assertSame("manutencao", $expense->category);
        $this->assertSame(5000, (int) $expense->amount);

        $entry = JournalEntry::firstWhere("source", "expense");
        $lines = $entry->lines()->get()->keyBy("account_id");
        $this->assertSame(5000, (int) $lines[Account::findByCode("6.3.2.9.9")->id]->debit);
        $this->assertSame(5000, (int) $lines[Account::findByCode("1.1")->id]->credit);
    }

    public function test_adding_part_creates_stock_exit(): void
    {
        $job = MaintenanceJob::create(array_merge($this->jobPayload(), [
            "equipment_id" => $this->equipment()->id,
            "cost" => 0,
            "status" => "in_progress",
            "completed_at" => null,
        ]));
        $part = $this->part(10);

        $this->actingAs($this->admin())->post("/resources/maintenance/{$job->id}/parts", [
            "inventory_item_id" => $part->id,
            "quantity" => 2,
        ])->assertSessionHasNoErrors();

        $this->assertSame("8.00", (string) $part->fresh()->stock_qty);
        $this->assertSame(1, $job->parts()->count());

        $movement = InventoryMovement::firstWhere("reason", "maintenance");
        $this->assertNotNull($movement);
        $this->assertSame("out", $movement->direction);
        $this->assertNull($movement->expense_id);
    }

    public function test_part_without_stock_is_rejected(): void
    {
        $job = MaintenanceJob::create(array_merge($this->jobPayload(), [
            "equipment_id" => $this->equipment()->id,
            "cost" => 0,
            "status" => "in_progress",
            "completed_at" => null,
        ]));
        $part = $this->part(1);

        $this->actingAs($this->admin())->post("/resources/maintenance/{$job->id}/parts", [
            "inventory_item_id" => $part->id,
            "quantity" => 5,
        ])->assertSessionHas("error", __("app.movement_insufficient_stock"));

        $this->assertSame("1.00", (string) $part->fresh()->stock_qty);
        $this->assertSame(0, $job->parts()->count());
    }

    public function test_removing_part_restores_stock(): void
    {
        $job = MaintenanceJob::create(array_merge($this->jobPayload(), [
            "equipment_id" => $this->equipment()->id,
            "cost" => 0,
            "status" => "in_progress",
            "completed_at" => null,
        ]));
        $part = $this->part(10);
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/maintenance/{$job->id}/parts", ["inventory_item_id" => $part->id, "quantity" => 2])->assertSessionHasNoErrors();

        $jobPart = $job->parts()->first();
        $this->actingAs($admin)->delete("/resources/maintenance/{$job->id}/parts/{$jobPart->id}")->assertSessionHasNoErrors();

        $this->assertSame("10.00", (string) $part->fresh()->stock_qty);
        $this->assertSame(0, $job->parts()->count());
        $this->assertSame(0, InventoryMovement::where("reason", "maintenance")->count());
    }

    public function test_maintenance_movement_cannot_be_edited_directly(): void
    {
        $job = MaintenanceJob::create(array_merge($this->jobPayload(), [
            "equipment_id" => $this->equipment()->id,
            "cost" => 0,
            "status" => "in_progress",
            "completed_at" => null,
        ]));
        $part = $this->part();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/maintenance/{$job->id}/parts", ["inventory_item_id" => $part->id, "quantity" => 1])->assertSessionHasNoErrors();

        $movement = InventoryMovement::firstWhere("reason", "maintenance");

        $this->actingAs($admin)->delete("/resources/movements/{$movement->id}")
            ->assertSessionHas("error", __("app.movement_locked_via_job"));
    }

    public function test_cancel_job_reverts_expense_and_parts(): void
    {
        $equipment = $this->equipment();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/maintenance", $this->jobPayload(["equipment_id" => $equipment->id]))->assertSessionHasNoErrors();

        $job = MaintenanceJob::first();
        $part = $this->part(10);
        $this->actingAs($admin)->post("/resources/maintenance/{$job->id}/parts", ["inventory_item_id" => $part->id, "quantity" => 3])->assertSessionHasNoErrors();

        $this->actingAs($admin)->patch("/resources/maintenance/{$job->id}/cancel")->assertSessionHasNoErrors();

        $job->refresh();
        $this->assertSame("cancelled", $job->status);
        $this->assertNull($job->expense_id);
        $this->assertSame(0, Expense::count());
        $this->assertSame(0, JournalEntry::where("source", "expense")->count());
        $this->assertSame(0, $job->parts()->count());
        $this->assertSame("10.00", (string) $part->fresh()->stock_qty);
    }

    public function test_cancelled_job_cannot_be_edited(): void
    {
        $equipment = $this->equipment();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/resources/maintenance", $this->jobPayload(["equipment_id" => $equipment->id]))->assertSessionHasNoErrors();

        $job = MaintenanceJob::first();
        $this->actingAs($admin)->patch("/resources/maintenance/{$job->id}/cancel")->assertSessionHasNoErrors();

        $this->actingAs($admin)->put("/resources/maintenance/{$job->id}", $this->jobPayload(["equipment_id" => $equipment->id]))
            ->assertSessionHas("error", __("app.job_cancelled_locked"));
    }

    public function test_preventive_due_counted_in_dashboard_alert(): void
    {
        MaintenanceJob::create(array_merge($this->jobPayload(), [
            "equipment_id" => $this->equipment()->id,
            "kind" => "preventive",
            "status" => "scheduled",
            "next_service_date" => now()->subDay()->toDateString(),
            "completed_at" => null,
        ]));

        $this->actingAs($this->admin())->get("/dashboard")
            ->assertOk()
            ->assertSee(__('app.maintenance_due'), false);
    }
}
