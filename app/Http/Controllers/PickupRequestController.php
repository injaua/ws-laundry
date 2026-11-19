<?php

namespace App\Http\Controllers;

use App\Models\CourierJob;
use App\Models\DeliveryZone;
use App\Services\Logistics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Public "request a pickup" form on the website; requests wait for the counter to confirm them. */
class PickupRequestController extends Controller
{
    public function store(Request $request, Logistics $logistics)
    {
        // Honeypot: bots fill every field; people never see this one.
        if ($request->filled("website")) {
            return redirect()->to(route("home") . "#pickup")->with("pickup_success", true);
        }

        $data = $request->validateWithBag("pickup", [
            "contact_name" => "required|string|max:100",
            "contact_phone" => ["required", "string", "max:30", "regex:/^[+\d\s()-]{9,}$/"],
            "address" => "required|string|max:255",
            "address_reference" => "nullable|string|max:255",
            "delivery_zone_id" => ["required", DeliveryZone::existsRule(activeOnly: true)],
            "scheduled_date" => "required|date|after_or_equal:today|before_or_equal:" . today()->addDays(30)->toDateString(),
            "time_window" => ["required", Rule::in(CourierJob::WINDOWS)],
            "notes" => "nullable|string|max:500",
        ]);

        // One open request per phone is enough; a second one just updates the first.
        $open = CourierJob::where("status", "requested")->where("contact_phone", $data["contact_phone"])->first();
        if ($open) {
            $open->update($data + ["fee" => DeliveryZone::find($data["delivery_zone_id"])->feeFor()]);
        } else {
            $logistics->schedulePickup($data, null, "website");
        }

        return redirect()->to(route("home") . "#pickup")->with("pickup_success", true);
    }
}
