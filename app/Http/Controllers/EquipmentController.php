<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentRecipe;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EquipmentController extends Controller
{
    private const PHOTO_RULES = "nullable|image|mimes:jpg,jpeg,png,webp|max:5120";

    public function index(Request $request)
    {
        $query = Equipment::withCount("jobs");

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                    ->orWhere("code", "like", "%{$search}%")
                    ->orWhere("brand", "like", "%{$search}%");
            });
        }

        if ($type = $request->type) {
            $query->where("type", $type);
        }

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        $equipments = $query->orderBy("name")->paginate(15)->withQueryString();

        return view("resources.equipments.index", compact("equipments"));
    }

    public function create()
    {
        return view("resources.equipments.create");
    }

    public function store(Request $request)
    {
        $validated = $this->validateEquipment($request);

        if ($request->hasFile("photo")) {
            $validated["photo"] = $request->file("photo")->store("equipments", "public");
        } else {
            unset($validated["photo"]);
        }

        Equipment::create($validated);

        return redirect()->route("resources.equipments.index")
            ->with("success", __('app.equipment_created'));
    }

    public function show(Equipment $equipment)
    {
        $equipment->load(["jobs" => fn ($q) => $q->with("employee")->latest("service_date"), "recipes.item"]);
        $loads = $equipment->loads()->with("employee:id,name")->withCount("orders")->latest("started_at")->limit(15)->get();
        $items = InventoryItem::where("is_active", true)->orderBy("name")->get(["id", "name", "unit"]);

        return view("resources.equipments.show", compact("equipment", "loads", "items"));
    }

    /** Per-cycle recipe: products the machine uses on every load. */
    public function storeRecipe(Request $request, Equipment $equipment)
    {
        $data = $request->validate([
            "inventory_item_id" => "required|integer|exists:inventory_items,id",
            "quantity" => "required|numeric|min:0.001|max:100000",
        ]);

        $equipment->recipes()->updateOrCreate(["inventory_item_id" => $data["inventory_item_id"]], ["quantity" => $data["quantity"]]);

        return back()->with("success", __("app.recipe_saved"));
    }

    public function destroyRecipe(Equipment $equipment, EquipmentRecipe $recipe)
    {
        abort_unless($recipe->equipment_id === $equipment->id, 404);
        $recipe->delete();

        return back()->with("success", __("app.recipe_removed"));
    }

    public function edit(Equipment $equipment)
    {
        return view("resources.equipments.edit", compact("equipment"));
    }

    public function update(Request $request, Equipment $equipment)
    {
        $validated = $this->validateEquipment($request, $equipment);

        if ($request->hasFile("photo")) {
            if ($equipment->photo) {
                Storage::disk("public")->delete($equipment->photo);
            }
            $validated["photo"] = $request->file("photo")->store("equipments", "public");
        } elseif ($request->boolean("remove_photo")) {
            if ($equipment->photo) {
                Storage::disk("public")->delete($equipment->photo);
            }
            $validated["photo"] = null;
        } else {
            unset($validated["photo"]);
        }
        unset($validated["remove_photo"]);

        $equipment->update($validated);

        return redirect()->route("resources.equipments.show", $equipment)
            ->with("success", __('app.equipment_updated'));
    }

    public function destroy(Equipment $equipment)
    {
        if ($equipment->jobs()->exists()) {
            return redirect()->route("resources.equipments.index")
                ->with("error", __('app.equipment_in_use'));
        }

        if ($equipment->photo) {
            Storage::disk("public")->delete($equipment->photo);
        }

        $equipment->delete();

        return redirect()->route("resources.equipments.index")
            ->with("success", __('app.equipment_deleted'));
    }

    private function validateEquipment(Request $request, ?Equipment $equipment = null): array
    {
        return $request->validate([
            "name" => "required|string|max:200",
            "code" => ["nullable", "string", "max:50", Rule::unique("equipments", "code")->ignore($equipment?->id)],
            "type" => "required|in:".implode(",", Equipment::TYPES),
            "brand" => "nullable|string|max:100",
            "model" => "nullable|string|max:100",
            "serial_number" => "nullable|string|max:100",
            "purchase_date" => "nullable|date",
            "purchase_price" => "nullable|numeric|min:0",
            "supplier" => "nullable|string|max:150",
            "warranty_until" => "nullable|date",
            "location" => "nullable|string|max:150",
            "status" => "required|in:".implode(",", Equipment::STATUSES),
            "photo" => self::PHOTO_RULES,
            "remove_photo" => "nullable|boolean",
            "notes" => "nullable|string|max:2000",
            "capacity_kg" => "nullable|numeric|min:0|max:1000",
            "service_interval_cycles" => "nullable|integer|min:1|max:1000000",
        ]);
    }
}
