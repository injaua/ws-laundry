<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\User;
use App\Services\Security\SecurityCheck;
use App\Support\CurrentStore;
use App\Support\RequestCache;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The name and logos of Admin → System settings, as each request / store sees them. */
class BrandingContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_titles_use_the_name_of_the_store_worked_in(): void
    {
        $store = Store::findOrFail(Store::defaultId());
        SiteSetting::setAt($store->id, "company_name", "Washly");

        $owner = User::factory()->owner()->create(["store_id" => $store->id]);
        $this->actingAs($owner)->get("/dashboard")->assertOk()->assertSee("<title>", false)->assertSee("- Washly</title>", false);
        $this->assertSame("Washly", config("app.name"));
    }

    public function test_pages_without_a_store_show_the_main_store_identity(): void
    {
        SiteSetting::setAt(Store::defaultId(), "company_name", "Washly");

        $this->get("/login")->assertOk()
            ->assertSee("- Washly</title>", false)
            ->assertSee(__("app.login_subtitle", ["company" => "Washly"]));
        $this->get("/")->assertOk()->assertSee("Washly</title>", false)->assertDontSee("Jasa laundry");
    }

    public function test_uploaded_logos_use_the_host_of_the_request_not_app_url(): void
    {
        // The real public disk: a fake one ignores the configured URL.
        config(["app.url" => "http://localhost"]);
        $path = "branding/test-" . uniqid() . ".png";
        SiteSetting::setAt(Store::defaultId(), "brand_logo", $path);
        $this->get("http://127.0.0.1:8000/login")->assertOk(); // ApplySystemSettings runs here
        Storage::disk("public")->put($path, "png");

        try {
            $this->assertStringStartsWith("http://127.0.0.1:8000/storage/{$path}?v=", SystemSettings::logoUrl());
        } finally {
            Storage::disk("public")->delete($path);
        }
    }

    public function test_a_missing_logo_file_falls_back_to_the_default_logo(): void
    {
        Storage::fake("public");
        SiteSetting::setAt(Store::defaultId(), "brand_logo", "branding/gone.png");

        CurrentStore::run(Store::defaultId(), function () {
            $this->assertSame(asset("images/logo.png"), SystemSettings::logoUrl());
        });
    }

    public function test_per_request_state_is_scoped_to_the_container(): void
    {
        RequestCache::instance()->put("current-store:found:1", "stale");
        $this->app->forgetScopedInstances(); // what Octane and the queue worker do between requests / jobs

        $this->assertFalse(RequestCache::instance()->has("current-store:found:1"));
    }

    public function test_app_url_check(): void
    {
        config(["app.url" => "https://lavandaria.example.com"]);
        $this->assertTrue(SecurityCheck::appUrlMatches("https://lavandaria.example.com"));
        $this->assertTrue(SecurityCheck::appUrlMatches(null));
        $this->assertFalse(SecurityCheck::appUrlMatches("http://127.0.0.1:8000"));

        config(["app.url" => ""]);
        $this->assertFalse(SecurityCheck::appUrlMatches(null));

        config(["app.url" => "http://localhost"]);
        $this->app["env"] = "production";
        $this->assertFalse(SecurityCheck::appUrlMatches(null));
    }
}
