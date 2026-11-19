<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Language;
use App\Models\Translation;
use App\Models\User;
use App\Support\Languages;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LanguagesCurrenciesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function createLanguage(array $overrides = []): Language
    {
        $this->actingAs($this->admin())->post(route("admin.languages.store"), array_merge([
            "code" => "fr", "name" => "Français", "flag" => "🇫🇷", "direction" => "ltr", "base_locale" => "en", "is_active" => 1,
        ], $overrides))->assertSessionHasNoErrors();

        return Language::where("code", $overrides["code"] ?? "fr")->firstOrFail();
    }

    public function test_admin_creates_a_language_that_starts_from_its_base(): void
    {
        $language = $this->createLanguage();
        $this->assertFalse($language->is_system);

        // Not translated yet: English texts, and it is offered in the language menu.
        $this->get(route("language.switch", "fr"))->assertRedirect();
        $this->get("/dashboard")->assertOk()
            ->assertSee(trans("app.dashboard", [], "en"))
            ->assertSee("🇫🇷 Français");
        $this->assertSame("fr", app()->getLocale());
    }

    public function test_translations_are_used_and_empty_restores_the_base(): void
    {
        $language = $this->createLanguage();

        $this->post(route("admin.languages.translations.save", $language), [
            "group" => "app",
            "t" => ["dashboard" => "Tableau de bord", "orders" => trans("app.orders", [], "en"), "unknown_key_xyz" => "x"],
        ])->assertSessionHasNoErrors();

        // Same text as the base and unknown keys are not stored.
        $this->assertSame(1, Translation::where("locale", "fr")->count());
        $this->assertSame("Tableau de bord", trans("app.dashboard", [], "fr"));

        $this->get(route("language.switch", "fr"));
        $this->get("/dashboard")->assertSee("Tableau de bord");

        $this->post(route("admin.languages.translations.save", $language), ["group" => "app", "t" => ["dashboard" => ""]]);
        $this->assertSame(0, Translation::where("locale", "fr")->count());
        $this->assertSame(trans("app.dashboard", [], "en"), trans("app.dashboard", [], "fr"));
    }

    public function test_nested_validation_messages_and_placeholder_warning(): void
    {
        $language = $this->createLanguage();

        $this->post(route("admin.languages.translations.save", $language), [
            "group" => "validation",
            "t" => ["between.numeric" => "Le champ :attribute doit être entre :min et :max.", "required" => "Le champ est obligatoire."],
        ])->assertSessionHas("warning"); // "required" lost :attribute

        $this->assertSame("Le champ prix doit être entre 1 et 5.", trans("validation.between.numeric", ["attribute" => "prix", "min" => 1, "max" => 5], "fr"));
        // The rest of the group still comes from the base language.
        $this->assertSame(trans("validation.email", ["attribute" => "x"], "en"), trans("validation.email", ["attribute" => "x"], "fr"));
    }

    public function test_built_in_language_texts_can_be_edited(): void
    {
        $pt = Language::where("code", "pt")->first();

        $this->actingAs($this->admin())->post(route("admin.languages.translations.save", $pt), [
            "group" => "app", "t" => ["dashboard" => "Painel principal"],
        ]);
        $this->assertSame("Painel principal", trans("app.dashboard", [], "pt"));
        $this->get(route("admin.languages.translations", ["language" => $pt, "group" => "app", "q" => "Painel principal"]))->assertOk()->assertSee("Painel principal");
    }

    public function test_export_and_import(): void
    {
        $language = $this->createLanguage();

        $export = $this->get(route("admin.languages.export", $language))->assertOk()->json();
        $this->assertArrayHasKey("app", $export);
        $this->assertSame(trans("app.dashboard", [], "en"), $export["app"]["dashboard"]);

        $file = UploadedFile::fake()->createWithContent("fr.json", json_encode(["app" => ["dashboard" => "Accueil", "logout" => "Déconnexion"], "auth" => ["failed" => "Identifiants incorrects."]]));
        $this->post(route("admin.languages.import", $language), ["file" => $file])->assertSessionHas("success");

        $this->assertSame("Accueil", trans("app.dashboard", [], "fr"));
        $this->assertSame("Identifiants incorrects.", trans("auth.failed", [], "fr"));
    }

    public function test_rtl_language_and_default_language(): void
    {
        $this->createLanguage(["code" => "ar", "name" => "العربية", "direction" => "rtl"]);

        $this->post(route("admin.system-settings.localization.update"), [
            "locale_default" => "ar", "timezone" => "UTC", "date_format" => "d/m/Y", "time_format" => "H:i",
            "currency_code" => "MZN", "number_format" => "dot_comma", "text_direction" => "ltr", "phone_country_code" => "+258",
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get("/login")->assertOk()->assertSee('dir="rtl"', false);
        $this->assertSame("ar", app()->getLocale());
    }

    public function test_language_rules(): void
    {
        $language = $this->createLanguage();
        $admin = $this->admin();

        // Invalid / duplicate code
        $this->actingAs($admin)->post(route("admin.languages.store"), ["code" => "FR!", "name" => "X", "direction" => "ltr", "base_locale" => "en"])->assertSessionHasErrors("code");
        $this->actingAs($admin)->post(route("admin.languages.store"), ["code" => "fr", "name" => "X", "direction" => "ltr", "base_locale" => "en"])->assertSessionHasErrors("code");

        // Inactive languages are not offered and cannot be chosen
        $this->put(route("admin.languages.update", $language), ["name" => "Français", "direction" => "ltr", "base_locale" => "en"])->assertSessionHasNoErrors();
        $this->assertFalse(Languages::isActive("fr"));
        $this->get(route("language.switch", "fr"));
        $this->get("/dashboard")->assertDontSee("Français");

        // Built-in and default languages cannot be deleted; others delete their texts too
        $this->delete(route("admin.languages.destroy", Language::where("code", "pt")->first()))->assertSessionHas("error");
        Translation::create(["locale" => "fr", "group" => "app", "key" => "dashboard", "value" => "x"]);
        $this->delete(route("admin.languages.destroy", $language))->assertSessionHas("success");
        $this->assertSame(0, Translation::where("locale", "fr")->count());

        // Cashiers cannot manage languages
        $this->actingAs(User::factory()->create(["role" => "cashier"]))->get(route("admin.languages.index"))->assertForbidden();
    }

    public function test_admin_creates_a_currency_and_makes_it_default(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route("admin.currencies.index"))->assertOk()->assertSee("MZN");

        $this->post(route("admin.currencies.store"), ["code" => "bwp", "name" => "Pula", "symbol" => "P", "position" => "before", "decimals" => 2, "is_active" => 1])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Currency::where("code", "BWP")->exists());

        $this->assertSame("1,250 Mts", money(1250));
        $bwp = Currency::where("code", "BWP")->first();
        $this->post(route("admin.currencies.default", $bwp))->assertSessionHas("success");

        $this->assertSame("BWP", SystemSettings::get("currency_code"));
        $this->assertSame("P 1,250.00", money(1250));
        $this->get(route("admin.system-settings.localization"))->assertOk()->assertSee("BWP · Pula", false);
    }

    public function test_currency_rules(): void
    {
        $admin = $this->admin();
        $mzn = Currency::where("code", "MZN")->first();

        $this->actingAs($admin)->post(route("admin.currencies.store"), ["code" => "MZN", "name" => "X", "symbol" => "X", "position" => "after", "decimals" => 0])->assertSessionHasErrors("code");
        $this->post(route("admin.currencies.store"), ["code" => "12", "name" => "X", "symbol" => "X", "position" => "after", "decimals" => 0])->assertSessionHasErrors("code");

        // The default currency can be neither deleted nor deactivated
        $this->delete(route("admin.currencies.destroy", $mzn))->assertSessionHas("error");
        $this->put(route("admin.currencies.update", $mzn), ["name" => "Metical", "symbol" => "Mts", "position" => "after", "decimals" => 0])->assertSessionHas("error");
        $this->assertTrue($mzn->fresh()->is_active);

        // Inactive currencies cannot become the default
        $usd = Currency::where("code", "USD")->first();
        $this->post(route("admin.currencies.default", $usd))->assertSessionHas("error");
        $this->post(route("admin.system-settings.localization.update"), [
            "locale_default" => "pt", "timezone" => "UTC", "date_format" => "d/m/Y", "time_format" => "H:i",
            "currency_code" => "USD", "number_format" => "dot_comma", "text_direction" => "ltr", "phone_country_code" => "+258",
        ])->assertSessionHasErrors("currency_code");

        $this->delete(route("admin.currencies.destroy", $usd))->assertSessionHas("success");
        $this->assertFalse(Currency::where("code", "USD")->exists());
    }
}
