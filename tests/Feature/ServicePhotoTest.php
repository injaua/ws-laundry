<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServicePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            "name" => "Express Wash",
            "type" => "by_weight",
            "price" => 500,
            "estimated_days" => 1,
        ], $extra);
    }

    public function test_admin_can_create_service_with_photo(): void
    {
        Storage::fake("public");

        $this->actingAs($this->admin())->post("/services", $this->payload([
            "photo" => UploadedFile::fake()->image("wash.png"),
        ]))->assertRedirect(route("services.index"));

        $service = Service::first();
        $this->assertNotNull($service->photo);
        Storage::disk("public")->assertExists($service->photo);
    }

    public function test_replacing_photo_deletes_the_old_file(): void
    {
        Storage::fake("public");
        $service = Service::create($this->payload(["photo" => "services/old.png"]));
        Storage::disk("public")->put("services/old.png", "x");

        $this->actingAs($this->admin())->put("/services/" . $service->id, $this->payload([
            "photo" => UploadedFile::fake()->image("new.png"),
        ]))->assertRedirect(route("services.index"));

        $service->refresh();
        Storage::disk("public")->assertMissing("services/old.png");
        $this->assertNotSame("services/old.png", $service->photo);
        Storage::disk("public")->assertExists($service->photo);
    }

    public function test_remove_photo_flag_clears_photo_and_file(): void
    {
        Storage::fake("public");
        $service = Service::create($this->payload(["photo" => "services/gone.png"]));
        Storage::disk("public")->put("services/gone.png", "x");

        $this->actingAs($this->admin())->put("/services/" . $service->id, $this->payload([
            "remove_photo" => "1",
        ]))->assertRedirect(route("services.index"));

        $service->refresh();
        $this->assertNull($service->photo);
        Storage::disk("public")->assertMissing("services/gone.png");
    }

    public function test_update_without_photo_touches_keeps_existing_photo(): void
    {
        Storage::fake("public");
        $service = Service::create($this->payload(["photo" => "services/keep.png"]));
        Storage::disk("public")->put("services/keep.png", "x");

        $this->actingAs($this->admin())->put("/services/" . $service->id, $this->payload(["price" => 800]))
            ->assertRedirect(route("services.index"));

        $service->refresh();
        $this->assertSame("services/keep.png", $service->photo);
        Storage::disk("public")->assertExists("services/keep.png");
    }

    public function test_deleting_service_removes_its_photo_file(): void
    {
        Storage::fake("public");
        $service = Service::create($this->payload(["photo" => "services/del.png"]));
        Storage::disk("public")->put("services/del.png", "x");

        $this->actingAs($this->admin())->delete("/services/" . $service->id)
            ->assertRedirect(route("services.index"));

        Storage::disk("public")->assertMissing("services/del.png");
    }

    public function test_service_photo_validation_rejects_non_images(): void
    {
        $this->actingAs($this->admin())->post("/services", $this->payload([
            "photo" => UploadedFile::fake()->create("virus.pdf", 10),
        ]))->assertSessionHasErrors("photo");

        $this->assertSame(0, Service::count());
    }
}
