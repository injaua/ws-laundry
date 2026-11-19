<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin → System settings → Currencies: the list the default currency is chosen from. */
class CurrencyController extends Controller
{
    public function index(): View
    {
        return view("admin.currencies.index", [
            "currencies" => Currency::orderByDesc("is_active")->orderBy("code")->get(),
            "default" => SystemSettings::get("currency_code"),
        ]);
    }

    public function create(): View
    {
        return view("admin.currencies.form", ["currency" => new Currency(["position" => "after", "decimals" => 2, "is_active" => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currency = Currency::create($this->validated($request));

        return redirect()->route("admin.currencies.index")->with("success", __("app.currency_created", ["code" => $currency->code]));
    }

    public function edit(Currency $currency): View
    {
        return view("admin.currencies.form", ["currency" => $currency]);
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $data = $this->validated($request, $currency);
        if (!$data["is_active"] && $currency->isDefault()) {
            return back()->withInput()->with("error", __("app.currency_default_cannot_deactivate"));
        }
        $currency->update($data);

        return redirect()->route("admin.currencies.index")->with("success", __("app.currency_saved"));
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->isDefault()) {
            return back()->with("error", __("app.currency_default_cannot_delete"));
        }
        $currency->delete();

        return redirect()->route("admin.currencies.index")->with("success", __("app.currency_deleted"));
    }

    /** Makes the currency the one every amount is shown in (amounts are not converted). */
    public function makeDefault(Currency $currency): RedirectResponse
    {
        if (!$currency->is_active) {
            return back()->with("error", __("app.currency_inactive"));
        }
        SystemSettings::save(["currency_code" => $currency->code]);

        return back()->with("success", __("app.currency_default_set", ["code" => $currency->code]));
    }

    private function validated(Request $request, ?Currency $currency = null): array
    {
        $request->merge(["code" => strtoupper((string) $request->input("code"))]);
        $data = $request->validate([
            "code" => $currency ? ["nullable"] : ["required", "regex:/^[A-Z]{3}$/", Rule::unique("currencies", "code")],
            "name" => "required|string|max:60",
            "symbol" => "required|string|max:10",
            "position" => "required|in:before,after",
            "decimals" => "required|integer|between:0,3",
            "is_active" => "nullable|boolean",
        ]);
        $data["is_active"] = $request->boolean("is_active");
        if ($currency) {
            unset($data["code"]); // the code identifies the currency and does not change
        }

        return $data;
    }
}
