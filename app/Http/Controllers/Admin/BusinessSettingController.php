<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class BusinessSettingController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::allAsArray();

        return view("admin.business-settings.index", compact("settings"));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            "vat_rate" => "required|numeric|between:0,100",
            // (Company identity is in Admin → System settings → General.)
            // Express (urgent) service surcharge.
            "express_enabled" => "nullable|boolean",
            "express_surcharge_type" => "nullable|in:percent,fixed",
            "express_surcharge_value" => ["nullable", "numeric", "min:0", $request->express_surcharge_type === "percent" ? "max:500" : "max:100000000"],
            "express_lead_days" => "nullable|integer|between:0,30",
            // Production: daily capacity (promise dates) and quality control.
            "production_capacity_kg" => "nullable|numeric|min:0|max:100000",
            "production_capacity_pieces" => "nullable|integer|min:0|max:100000",
            "qc_required" => "nullable|boolean",
            // Payments: who may pay by cheque, and the most cash a till should hold.
            "cheque_policy" => "nullable|in:" . implode(",", Cheque::POLICIES),
            "cash_till_limit" => "nullable|integer|min:0|max:1000000000",
        ]);

        $validated["express_enabled"] = $request->boolean("express_enabled") ? "1" : "0";
        $validated["qc_required"] = $request->boolean("qc_required") ? "1" : "0";

        foreach ($validated as $key => $value) {
            SiteSetting::set($key, $value ?? "");
        }

        return redirect()->route("admin.business-settings.index")
            ->with("success", __('app.business_settings_saved'));
    }
}
