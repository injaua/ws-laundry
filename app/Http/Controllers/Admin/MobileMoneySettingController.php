<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MobileTransaction;
use App\Services\MobileMoney\MobileMoneySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Operator credentials and mode (off / simulation / sandbox / live). */
class MobileMoneySettingController extends Controller
{
    public function edit(): View
    {
        $providers = [];
        foreach (MobileTransaction::PROVIDERS as $provider) {
            $settings = MobileMoneySettings::get($provider);
            foreach (MobileMoneySettings::FIELDS[$provider] as $field => $secret) {
                if ($secret) {
                    $settings[$field] = null; // never sent back to the browser
                }
            }
            $providers[$provider] = [
                "mode" => MobileMoneySettings::mode($provider),
                "settings" => $settings,
                "configured" => MobileMoneySettings::configured($provider),
                "secrets" => collect(MobileMoneySettings::FIELDS[$provider])->filter()->keys()
                    ->mapWithKeys(fn ($f) => [$f => MobileMoneySettings::hasSecret($provider, $f)]),
            ];
        }

        return view("admin.mobile-money.edit", ["providers" => $providers]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, MobileTransaction::PROVIDERS, true), 404);

        $rules = ["mode" => ["required", Rule::in(MobileMoneySettings::MODES)]];
        foreach (array_keys(MobileMoneySettings::FIELDS[$provider]) as $field) {
            $rules[$field] = in_array($field, ["sandbox_url", "live_url"], true) ? "nullable|url|max:255" : "nullable|string|max:4000";
        }
        $data = $request->validate($rules);

        MobileMoneySettings::save($provider, $data);

        if (in_array($data["mode"], ["sandbox", "live"], true) && !MobileMoneySettings::configured($provider)) {
            return back()->with("warning", __("app.mm_settings_incomplete"));
        }

        return back()->with("success", __("app.mm_settings_saved", ["provider" => __("app.$provider")]));
    }
}
