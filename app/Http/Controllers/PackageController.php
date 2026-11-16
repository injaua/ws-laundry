<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;

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
        return view("packages.edit", compact("package"));
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
}
