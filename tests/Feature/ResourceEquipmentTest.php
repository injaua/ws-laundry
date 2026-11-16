<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Equipment;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_cannot_access_resources_module(): void
    {
        $cashier = User::factory()->cashier()->create();

        foreach (["/resources/equipments", "/resources/items?kind=product", "/resources/items?kind=material", "/resources/maintenance"] as $url) {
            $this->actingAs($cashier)->get($url)->assertForbidden();
        }

        $this->actingAs($cashier)->post("/resources/equipments", ["name" => "X", "type" => "washer", "status" => "operational"])->assertForbidden();
    }

    public function test_owner_can_register_equipment(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post("/resources/equipments", [
            "name" => "Máquina de lavar 15kg",
            "code" => "EQ-001",
            "type" => "washer",
            "brand" => "LG",
            "status" => "operational",
        ])->assertRedirect(route("resources.equipments.index"))
            ->assertSessionHas("success", __("app.equipment_created"));

        $this->assertDatabaseHas("equipments", ["code" => "EQ-001", "type" => "washer"]);
    }

    public function test_equipment_photo_upload_and_removal(): void
    {
        Storage::fake("public");
        $admin = User::factory()->create();
        $equipment = Equipment::create(["name" => "Secadora", "type" => "dryer", "status" => "operational"]);

        $this->actingAs($admin)->put("/resources/equipments/{$equipment->id}", [
            "name" => "Secadora", "type" => "dryer", "status" => "operational",
            "photo" => UploadedFile::fake()->image("secadora.jpg"),
        ])->assertSessionHasNoErrors();

        $equipment->refresh();
        $this->assertNotNull($equipment->photo);
        Storage::disk("public")->assertExists($equipment->photo);

        $this->actingAs($admin)->put("/resources/equipments/{$equipment->id}", [
            "name" => "Secadora", "type" => "dryer", "status" => "operational",
            "remove_photo" => 1,
        ])->assertSessionHasNoErrors();

        $this->assertNull($equipment->fresh()->photo);
    }

    public function test_equipment_with_maintenance_jobs_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $equipment = Equipment::create(["name" => "Ferro", "type" => "iron", "status" => "operational"]);
        MaintenanceJob::create([
            "equipment_id" => $equipment->id,
            "kind" => "corrective",
            "title" => "Troca de resistência",
            "service_date" => now()->toDateString(),
            "status" => "completed",
        ]);

        $this->actingAs($admin)->delete("/resources/equipments/{$equipment->id}")
            ->assertSessionHas("error", __("app.equipment_in_use"));

        $this->assertDatabaseHas("equipments", ["id" => $equipment->id]);
    }

    public function test_equipment_ficha_lists_maintenance_history(): void
    {
        $admin = User::factory()->create();
        $equipment = Equipment::create(["name" => "Mesa de engomar", "type" => "ironing_table", "status" => "operational"]);
        $job = MaintenanceJob::create([
            "equipment_id" => $equipment->id,
            "kind" => "preventive",
            "title" => "Revisão trimestral",
            "service_date" => now()->toDateString(),
            "status" => "completed",
        ]);

        $this->actingAs($admin)->get("/resources/equipments/{$equipment->id}")
            ->assertOk()
            ->assertSee("Revisão trimestral")
            ->assertSee(__('app.maintenance_status_' . $job->status), false);
    }

    public function test_loggable_records_activity(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/resources/equipments", [
            "name" => "Gerador", "type" => "other", "status" => "operational",
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas("activity_logs", ["subject_type" => Equipment::class, "action" => "created"]);
    }
}
