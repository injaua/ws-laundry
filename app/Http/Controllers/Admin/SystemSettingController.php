<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Support\Languages;
use App\Support\SystemSettings;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin → System settings: General (company identity, branding) and Localization. */
class SystemSettingController extends Controller
{
    public function general(): View
    {
        return view("admin.system-settings.general", ["settings" => SystemSettings::all()]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "company_name" => "required|string|max:100",
            "company_legal_name" => "required|string|max:150",
            "company_nuit" => ["nullable", "regex:/^\d{9}$/"],
            "company_phone" => "nullable|string|max:50",
            "company_email" => "nullable|email|max:150",
            "company_address" => "nullable|string|max:255",
            "company_share_capital" => "nullable|string|max:50",
            "invoice_bank_details" => "nullable|string|max:500",
            "document_footer" => "nullable|string|max:500",
            // Images only (no SVG: it can carry scripts).
            "brand_logo" => "nullable|file|mimes:png,jpg,jpeg,webp|max:2048",
            "brand_logo_dark" => "nullable|file|mimes:png,jpg,jpeg,webp|max:2048",
            "brand_favicon" => "nullable|file|mimes:png,ico|max:512",
            "remove" => "nullable|array",
            "remove.*" => [Rule::in(SystemSettings::BRANDING_FILES)],
        ]);

        $values = collect($data)->except(array_merge(SystemSettings::BRANDING_FILES, ["remove"]))->all();
        $disk = Storage::disk("public");

        foreach (SystemSettings::BRANDING_FILES as $key) {
            $old = SystemSettings::get($key);
            if ($request->hasFile($key)) {
                $values[$key] = $request->file($key)->store("branding", "public");
            } elseif (in_array($key, $data["remove"] ?? [], true)) {
                $values[$key] = "";
            } else {
                continue;
            }
            if ($old !== "" && $old !== $values[$key] && str_starts_with($old, "branding/")) {
                $disk->delete($old);
            }
        }

        SystemSettings::save($values);

        return redirect()->route("admin.system-settings.general")->with("success", __("app.system_settings_saved"));
    }

    public function localization(): View
    {
        return view("admin.system-settings.localization", [
            "settings" => SystemSettings::all(),
            "examples" => SystemSettings::examples(),
            "timezones" => DateTimeZone::listIdentifiers(),
            "languages" => Languages::active(),
            "currencies" => Currency::where("is_active", true)->orderBy("code")->get(),
        ]);
    }

    public function updateLocalization(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "locale_default" => ["required", Rule::in(Languages::active()->keys()->all())],
            "timezone" => ["required", Rule::in(DateTimeZone::listIdentifiers())],
            "date_format" => ["required", Rule::in(SystemSettings::DATE_FORMATS)],
            "time_format" => ["required", Rule::in(SystemSettings::TIME_FORMATS)],
            "currency_code" => ["required", Rule::exists("currencies", "code")->where("is_active", true)],
            "number_format" => ["required", Rule::in(array_keys(SystemSettings::NUMBER_FORMATS))],
            "text_direction" => "required|in:ltr,rtl",
            "phone_country_code" => ["required", "regex:/^\+\d{1,4}$/"],
        ]);

        SystemSettings::save($data);

        return redirect()->route("admin.system-settings.localization")->with("success", __("app.system_settings_saved"));
    }
}
