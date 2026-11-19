<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ChoosesSettingsLevel;
use App\Http\Controllers\Controller;
use App\Models\MobileTransaction;
use App\Models\Payment;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\Payments\PaymentMethods;
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Payments: the accepted payment methods, and the operators' credentials and mode
 * (off / simulation / sandbox / live). For the company, or for the store worked in when it
 * has its own payment configuration (?level=company|store).
 */
class MobileMoneySettingController extends Controller
{
    use ChoosesSettingsLevel;

    public function edit(Request $request): View
    {
        $level = $this->settingsLevel($request, "payments");

        $providers = [];
        foreach (MobileTransaction::PROVIDERS as $provider) {
            $settings = MobileMoneySettings::get($provider, $level);
            foreach (MobileMoneySettings::FIELDS[$provider] as $field => $secret) {
                if ($secret) {
                    $settings[$field] = null; // never sent back to the browser
                }
            }
            $providers[$provider] = [
                "mode" => MobileMoneySettings::mode($provider, $level),
                "settings" => $settings,
                "configured" => MobileMoneySettings::configured($provider, $level),
                "secrets" => collect(MobileMoneySettings::FIELDS[$provider])->filter()->keys()
                    ->mapWithKeys(fn ($f) => [$f => MobileMoneySettings::hasSecret($provider, $f, $level)]),
            ];
        }

        return view("admin.mobile-money.edit", [
            "providers" => $providers,
            "methods" => Payment::METHODS,
            "enabledMethods" => PaymentMethods::enabled($level),
            "levelBar" => $this->levelBar("payments", $level, "admin.mobile-money.edit"),
            "levelParam" => $level === null ? "company" : "store",
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, MobileTransaction::PROVIDERS, true), 404);

        $rules = [
            "level" => ["nullable", Rule::in(["company", "store"])],
            "mode" => ["required", Rule::in(MobileMoneySettings::MODES)],
        ];
        foreach (array_keys(MobileMoneySettings::FIELDS[$provider]) as $field) {
            $rules[$field] = in_array($field, ["sandbox_url", "live_url"], true) ? "nullable|url|max:255" : "nullable|string|max:4000";
        }
        $data = $request->validate($rules);
        $level = $this->settingsLevel($request, "payments", forSaving: true);

        MobileMoneySettings::save($provider, $data, $level);

        $back = redirect()->route("admin.mobile-money.edit", $this->levelQuery($level, "payments"));
        if (in_array($data["mode"], ["sandbox", "live"], true) && !MobileMoneySettings::configured($provider, $level)) {
            return $back->with("warning", __("app.mm_settings_incomplete"));
        }

        return $back->with("success", __("app.mm_settings_saved", ["provider" => __("app.$provider")]));
    }

    /** Payment methods accepted for new payments (at least one). */
    public function updateMethods(Request $request): RedirectResponse
    {
        $data = $request->validate([
            "level" => ["nullable", Rule::in(["company", "store"])],
            "methods" => ["required", "array", "min:1"],
            "methods.*" => [Rule::in(Payment::METHODS)],
        ], ["methods.required" => __("app.payment_methods_required"), "methods.min" => __("app.payment_methods_required")]);
        $level = $this->settingsLevel($request, "payments", forSaving: true);

        PaymentMethods::save($data["methods"], $level);

        return redirect()->route("admin.mobile-money.edit", $this->levelQuery($level, "payments"))->with("success", __("app.payment_methods_saved"));
    }

    /** The store worked in goes back to the company payment configuration. */
    public function release(): RedirectResponse
    {
        $storeId = CurrentStore::id();
        abort_unless($storeId && StoreSettings::owns("payments", $storeId), 404);

        StoreSettings::release("payments", $storeId);

        return redirect()->route("admin.mobile-money.edit", ["level" => "store"])->with("success", __("app.store_settings_released"));
    }
}
