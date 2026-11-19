<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\PackageRecipe;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::query();

        if ($search = $request->search) {
            $query->where("name", "like", "%{$search}%");
        }

        $packages = $query->latest()->paginate(15);

        return view("packages.index", compact("packages"));
    }

    public function create()
    {
        return view("packages.create");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "description" => "nullable|string",
            "weight_kg" => "required|numeric|min:0.1",
            "price" => "required|numeric|min:0|max:10000",
            "is_active" => "nullable|boolean",
        ]);

        $validated["is_active"] = $request->boolean("is_active", true);

        Package::create($validated);

        return redirect()->route("packages.index")
            ->with("success", __('app.package_added'));
    }

    public function edit(Package $package)
    {
        $recipes = $package->recipes()->with("item")->get()
            ->sortBy(fn ($recipe) => array_search($recipe->stage, PackageRecipe::STAGES) * 1000 + $recipe->id)
            ->values();
        $recipeItems = InventoryItem::whereIn("kind", ["product", "material"])
            ->where("is_active", true)
            ->orderBy("name")
            ->get();

        return view("packages.edit", compact("package", "recipes", "recipeItems"));
    }

    public function update(Request $request, Package $package)
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "description" => "nullable|string",
            "weight_kg" => "required|numeric|min:0.1",
            "price" => "required|numeric|min:0|max:10000",
            "is_active" => "nullable|boolean",
        ]);

        $validated["is_active"] = $request->boolean("is_active", true);

        $package->update($validated);

        return redirect()->route("packages.index")
            ->with("success", __('app.package_updated'));
    }

    public function destroy(Package $package)
    {
        $package->delete();

        return redirect()->route("packages.index")
            ->with("success", __('app.package_deleted'));
    }

    public function storeRecipe(Request $request, Package $package)
    {
        $validated = $request->validate([
            "inventory_item_id" => [
                "required",
                Rule::unique("package_recipes", "inventory_item_id")->where(
                    fn ($query) => $query
                        ->where("package_id", $package->id)
                        ->where("stage", (string) $request->input("stage", ""))
                ),
                Rule::exists("inventory_items", "id")->where("is_active", true),
            ],
            "quantity" => "required|numeric|min:0.001",
            "basis" => "required|in:".implode(",", PackageRecipe::BASES),
            "stage" => "required|in:".implode(",", PackageRecipe::STAGES),
        ]);

        $package->recipes()->create($validated);

        return redirect()->route("packages.edit", $package)
            ->with("success", __('app.recipe_added'));
    }

    public function destroyRecipe(Package $package, PackageRecipe $recipe)
    {
        abort_unless($recipe->package_id === $package->id, 404);

        $recipe->delete();

        return redirect()->route("packages.edit", $package)
            ->with("success", __('app.recipe_deleted'));
    }
}
