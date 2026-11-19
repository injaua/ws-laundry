<?php

namespace Tests\Feature;

use App\Models\Gallery;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingContentFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_forms_post_to_the_store_and_update_routes(): void
    {
        Storage::fake("public");
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route("admin.galleries.create"))
            ->assertOk()
            ->assertSee('action="' . route("admin.galleries.store") . '"', false)
            ->assertDontSee("Urutan");

        $this->actingAs($admin)->post(route("admin.galleries.store"), [
            "photo" => UploadedFile::fake()->image("photo.jpg"),
            "caption" => "Front desk",
            "sort_order" => 2,
            "is_active" => 1,
        ])->assertRedirect(route("admin.galleries.index"));

        $gallery = Gallery::firstOrFail();
        $this->assertSame("Front desk", $gallery->caption);

        $this->actingAs($admin)->get(route("admin.galleries.edit", $gallery))
            ->assertOk()
            ->assertSee('action="' . route("admin.galleries.update", $gallery) . '"', false);
    }

    public function test_photo_dropped_by_php_upload_limit_explains_why(): void
    {
        $admin = User::factory()->create();
        $tmp = tempnam(sys_get_temp_dir(), "upl");
        $dropped = new UploadedFile($tmp, "big.jpg", "image/jpeg", UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($admin)->from(route("admin.galleries.create"))
            ->post(route("admin.galleries.store"), ["photo" => $dropped, "sort_order" => 0])
            ->assertRedirect(route("admin.galleries.create"))
            ->assertSessionHasErrors(["photo" => __("validation.uploaded", ["attribute" => "photo"])]);

        $this->assertSame(0, Gallery::count());
    }

    public function test_request_above_post_max_size_shows_a_friendly_page(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->call("POST", route("admin.galleries.store"), [], [], [], ["CONTENT_LENGTH" => 1024 * 1024 * 1024])
            ->assertStatus(413)
            ->assertSee(__("app.upload_too_large_title"));
    }

    public function test_testimonial_forms_post_to_the_store_and_update_routes(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route("admin.testimonials.create"))
            ->assertOk()
            ->assertSee('action="' . route("admin.testimonials.store") . '"', false)
            ->assertDontSee("Urutan");

        $this->actingAs($admin)->post(route("admin.testimonials.store"), [
            "customer_name" => "Ana",
            "content" => "Great service",
            "rating" => 5,
            "sort_order" => 1,
            "is_active" => 1,
        ])->assertRedirect(route("admin.testimonials.index"));

        $testimonial = Testimonial::firstOrFail();

        $this->actingAs($admin)->get(route("admin.testimonials.edit", $testimonial))
            ->assertOk()
            ->assertSee('action="' . route("admin.testimonials.update", $testimonial) . '"', false);
    }
}
