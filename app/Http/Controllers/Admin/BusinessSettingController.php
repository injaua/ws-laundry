<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $request->validate([
            "iva_rate" => "required|numeric|between:0,100",
        ]);

        SiteSetting::set("iva_rate", $request->iva_rate);

        return redirect()->route("admin.business-settings.index")
            ->with("success", __('app.business_settings_saved'));
    }
}
