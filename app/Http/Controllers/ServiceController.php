<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Service;
use App\Models\ServiceRecipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    private const PHOTO_RULES = "nullable|image|mimes:jpg,jpeg,png,webp|max:5120";

    public function index(Request $request)
    {
        $query = Service::query();

        if ($search = $request->search) {
            $query->where("name", "like", "%{$search}%");
        }

        $services = $query->latest()->paginate(15);

        return view("services.index", compact("services"));
    }

    public function create()
    {
        return view("services.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "type" => "required|in:by_weight,by_piece",
            "price" => "required|numeric|min:0|max:10000",
            "estimated_days" => "required|integer|min:1",
            "photo" => self::PHOTO_RULES,
        ]);

        if ($request->hasFile("photo")) {
            $validated["photo"] = $request->file("photo")->store("services", "public");
        } else {
            unset($validated["photo"]);
        }

        Service::create($validated);

        return redirect()->route("services.index")
            ->with("success", __('app.service_added'));
    }

    public function edit(Service $service)
    {
        $recipes = $service->recipes()->with("item")->get()
            ->sortBy(fn ($recipe) => array_search($recipe->stage, ServiceRecipe::STAGES) * 1000 + $recipe->id)
            ->values();
        $recipeItems = InventoryItem::whereIn("kind", ["product", "material"])
            ->where("is_active", true)
            ->orderBy("name")
            ->get();

        return view("services.edit", compact("service", "recipes", "recipeItems"));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "type" => "required|in:by_weight,by_piece",
            "price" => "required|numeric|min:0|max:10000",
            "estimated_days" => "required|integer|min:1",
            "photo" => self::PHOTO_RULES,
            "remove_photo" => "nullable|boolean",
        ]);

        if ($request->hasFile("photo")) {
            if ($service->photo) {
                Storage::disk("public")->delete($service->photo);
            }
            $validated["photo"] = $request->file("photo")->store("services", "public");
        } elseif ($request->boolean("remove_photo")) {
            if ($service->photo) {
                Storage::disk("public")->delete($service->photo);
            }
            $validated["photo"] = null;
        } else {
            unset($validated["photo"]);
        }
        unset($validated["remove_photo"]);

        $service->update($validated);

        return redirect()->route("services.index")
            ->with("success", __('app.service_updated'));
    }

    public function storeRecipe(Request $request, Service $service)
    {
        $validated = $request->validate([
            "inventory_item_id" => [
                "required",
                Rule::unique("service_recipes", "inventory_item_id")->where(
                    fn ($query) => $query
                        ->where("service_id", $service->id)
                        ->where("stage", (string) $request->input("stage", ""))
                ),
                Rule::exists("inventory_items", "id")->where("is_active", true),
            ],
            "quantity" => "required|numeric|min:0.001",
            "basis" => "required|in:".implode(",", ServiceRecipe::BASES),
            "stage" => "required|in:".implode(",", ServiceRecipe::STAGES),
        ]);

        $service->recipes()->create($validated);

        return redirect()->route("services.edit", $service)
            ->with("success", __('app.recipe_added'));
    }

    public function destroyRecipe(Service $service, ServiceRecipe $recipe)
    {
        abort_unless($recipe->service_id === $service->id, 404);

        $recipe->delete();

        return redirect()->route("services.edit", $service)
            ->with("success", __('app.recipe_deleted'));
    }

    public function destroy(Service $service)
    {
        if ($service->orderItems()->exists()) {
            return redirect()->route("services.index")
                ->with("error", __('app.service_delete_blocked'));
        }

        if ($service->photo) {
            Storage::disk("public")->delete($service->photo);
        }

        $service->delete();

        return redirect()->route("services.index")
            ->with("success", __('app.service_deleted'));
    }
}
