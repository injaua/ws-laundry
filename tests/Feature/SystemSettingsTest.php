<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\User;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function localization(array $overrides = []): array
    {
        return array_merge([
            "locale_default" => "pt", "timezone" => "UTC", "date_format" => "d/m/Y", "time_format" => "H:i",
            "currency_code" => "MZN",
            "number_format" => "dot_comma", "text_direction" => "ltr", "phone_country_code" => "+258",
        ], $overrides);
    }

    public function test_pages_are_for_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->get(route("admin.system-settings.general"))->assertOk()->assertSee(__("app.company_trade_name"));
        // System settings are not part of a store's settings: the owner neither sees nor edits them.
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner)->get(route("admin.system-settings.general"))->assertForbidden();
        $this->actingAs($owner)->post(route("admin.system-settings.general.update"), ["company_name" => "X"])->assertForbidden();
        $this->actingAs($owner)->get("/dashboard")->assertOk()->assertDontSee(route("admin.system-settings.general"));
        // Localization is company-wide (every store): admin only.
        $this->actingAs(User::factory()->create())->get(route("admin.system-settings.localization"))->assertOk()->assertSee(__("app.default_timezone"));
        $this->actingAs(User::factory()->owner()->create())->get(route("admin.system-settings.localization"))->assertForbidden();

        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->actingAs($cashier)->get(route("admin.system-settings.general"))->assertForbidden();
        $this->actingAs($cashier)->post(route("admin.system-settings.localization.update"), $this->localization())->assertForbidden();
    }

    public function test_menu_shows_system_settings_with_two_entries(): void
    {
        $this->actingAs(User::factory()->create())->get("/dashboard")->assertOk()
            ->assertSee(__("app.system_settings"))
            ->assertSee(route("admin.system-settings.general"))
            ->assertSee(route("admin.system-settings.localization"));
    }

    public function test_general_settings_are_saved_and_used(): void
    {
        Storage::fake("public");
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route("admin.system-settings.general.update"), [
            "company_name" => "Lava Bem",
            "company_legal_name" => "Lava Bem Serviços, Lda",
            "company_nuit" => "400999888",
            "company_phone" => "+258 84 000 0000",
            "company_email" => "geral@lavabem.co.mz",
            "company_address" => "Av. 24 de Julho, Maputo",
            "company_share_capital" => "500.000,00 MT",
            "document_footer" => "Processado por computador.",
            "brand_logo" => UploadedFile::fake()->image("logo.png", 200, 80),
            "brand_favicon" => UploadedFile::fake()->image("icon.png", 64, 64),
        ])->assertRedirect(route("admin.system-settings.general"))->assertSessionHasNoErrors();

        $logo = SystemSettings::get("brand_logo");
        $this->assertStringStartsWith("branding/", $logo);
        Storage::disk("public")->assertExists($logo);

        $this->get("/dashboard")->assertOk()
            ->assertSee("Lava Bem")
            ->assertSee(Storage::disk("public")->url($logo), false)
            ->assertSee(Storage::disk("public")->url(SystemSettings::get("brand_favicon")), false);

        // Printed documents: legal name, NUIT, share capital and footer.
        $customer = Customer::create(["name" => "Empresa Y", "phone" => "+258841110000", "type" => "business", "nuit" => "400000777", "is_credit_customer" => true]);
        $this->get(route("customers.statement", $customer))->assertOk()
            ->assertSee("Lava Bem Serviços, Lda")->assertSee("400999888")->assertSee("500.000,00 MT")->assertSee("Processado por computador.");

        // Removing the logo deletes the file and falls back to the default image.
        $this->post(route("admin.system-settings.general.update"), ["company_name" => "Lava Bem", "company_legal_name" => "Lava Bem Serviços, Lda", "remove" => ["brand_logo"]]);
        Storage::disk("public")->assertMissing($logo);
        $this->assertSame("", SystemSettings::get("brand_logo"));
        $this->assertStringContainsString("images/logo.png", SystemSettings::logoUrl());
    }

    public function test_svg_logo_is_rejected(): void
    {
        Storage::fake("public");
        $this->actingAs(User::factory()->create())->post(route("admin.system-settings.general.update"), [
            "company_name" => "X", "company_legal_name" => "X, Lda",
            "brand_logo" => UploadedFile::fake()->createWithContent("logo.svg", '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])->assertSessionHasErrors("brand_logo");
    }

    public function test_currency_and_number_format(): void
    {
        $this->assertSame("1,250 Mts", money(1250));

        $admin = User::factory()->create();
        $this->actingAs($admin)->put(route("admin.currencies.update", Currency::where("code", "MZN")->first()), [
            "name" => "Metical", "symbol" => "MT", "position" => "before", "decimals" => 2, "is_active" => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route("admin.system-settings.localization.update"), $this->localization(["number_format" => "comma_dot"]))->assertSessionHasNoErrors();

        $this->assertSame("MT 1.234,50", money(1234.5));
        $this->assertSame("-MT 10,00", money(-10));
        $this->assertSame("2,5", qty(2.50));
        $this->assertSame("MT", currency_symbol());
        $this->get(route("admin.system-settings.localization"))->assertSee("MT 1.234.567,50");
    }

    public function test_date_format_and_timezone(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route("admin.system-settings.localization.update"), $this->localization(["date_format" => "Y-m-d", "time_format" => "h:i A", "timezone" => "Africa/Maputo"]))
            ->assertSessionHasNoErrors();

        $this->assertSame("Y-m-d", date_fmt());
        $this->assertSame("Y-m-d h:i A", datetime_fmt());
        $this->assertSame("2026-11-19", SystemSettings::formatDate(Carbon::parse("2026-11-19 10:00")));

        SystemSettings::apply();
        $this->assertSame("Africa/Maputo", config("app.timezone"));
        $this->assertSame("Africa/Maputo", date_default_timezone_get());
        date_default_timezone_set("UTC");
    }

    public function test_invalid_localization_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route("admin.system-settings.localization.update"), $this->localization(["timezone" => "Mars/Base", "date_format" => "Y", "currency_code" => "mt", "phone_country_code" => "258"]))
            ->assertSessionHasErrors(["timezone", "date_format", "currency_code", "phone_country_code"]);
    }

    public function test_default_language_for_new_visitors(): void
    {
        SystemSettings::save(["locale_default" => "en"]);
        $this->get("/login")->assertOk()->assertSee(trans("app.login", [], "en"));
        $this->assertSame("en", app()->getLocale());
    }

    public function test_rtl_layout(): void
    {
        // Vite as a dev server (no build needed): the tags show which Bootstrap entry was chosen.
        $this->withVite();
        $hot = tempnam(sys_get_temp_dir(), "vite-hot");
        file_put_contents($hot, "http://vite.test");
        $this->beforeApplicationDestroyed(fn () => @unlink($hot));
        Vite::useHotFile($hot);

        $user = User::factory()->create();
        $this->actingAs($user)->get("/dashboard")->assertOk()
            ->assertSee("resources/css/vendor/bootstrap.css", false)
            ->assertDontSee("resources/css/vendor/bootstrap-rtl.css", false);

        SystemSettings::save(["text_direction" => "rtl"]);

        $this->actingAs($user)->get("/dashboard")->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee("resources/css/vendor/bootstrap-rtl.css", false)
            ->assertDontSee("resources/css/vendor/bootstrap.css", false);
    }

    public function test_phone_country_code_in_whatsapp_links(): void
    {
        $customer = Customer::create(["name" => "Ana", "phone" => "841234567"]);
        $this->assertSame("258841234567", $customer->normalizedWhatsapp());

        SystemSettings::save(["phone_country_code" => "+27"]);
        $this->assertSame("27841234567", $customer->normalizedWhatsapp());
    }

    public function test_business_settings_point_to_system_settings(): void
    {
        $this->actingAs(User::factory()->create())->get(route("admin.business-settings.index"))->assertOk()
            ->assertSee(route("admin.system-settings.general"))
            ->assertDontSee('name="company_nuit"', false);
    }
}
