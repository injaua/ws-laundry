<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeliveryZoneController extends Controller
{
    public function index()
    {
        $zones = DeliveryZone::withCount(["jobs", "customers"])->orderBy("sort_order")->orderBy("name")->get();

        return view("logistics.zones.index", compact("zones"));
    }

    public function create()
    {
        return view("logistics.zones.form", ["zone" => new DeliveryZone(["is_active" => true, "fee" => 0])]);
    }

    public function store(Request $request)
    {
        DeliveryZone::create($this->validated($request));

        return redirect()->route("delivery-zones.index")->with("success", __("app.zone_saved"));
    }

    public function edit(DeliveryZone $deliveryZone)
    {
        return view("logistics.zones.form", ["zone" => $deliveryZone]);
    }

    public function update(Request $request, DeliveryZone $deliveryZone)
    {
        $deliveryZone->update($this->validated($request, $deliveryZone));

        return redirect()->route("delivery-zones.index")->with("success", __("app.zone_saved"));
    }

    /** Zones already used keep the history: they are switched off instead. */
    public function destroy(DeliveryZone $deliveryZone)
    {
        if ($deliveryZone->jobs()->exists()) {
            $deliveryZone->update(["is_active" => false]);

            return redirect()->route("delivery-zones.index")->with("success", __("app.zone_deactivated"));
        }

        $deliveryZone->delete();

        return redirect()->route("delivery-zones.index")->with("success", __("app.zone_deleted"));
    }

    private function validated(Request $request, ?DeliveryZone $zone = null): array
    {
        $data = $request->validate([
            // Names repeat across stores (each store keeps its own zones), never within one.
            "name" => ["required", "string", "max:100", Rule::unique("delivery_zones")->where("store_id", CurrentStore::id())->ignore($zone?->id)],
            "description" => "nullable|string|max:1000",
            "fee" => "required|numeric|min:0|max:1000000",
            "free_from" => "nullable|numeric|min:0|max:100000000",
            "sort_order" => "nullable|integer|min:0|max:9999",
        ]);

        $data["is_active"] = $request->boolean("is_active");
        $data["sort_order"] = (int) ($data["sort_order"] ?? 0);

        return $data;
    }
}
