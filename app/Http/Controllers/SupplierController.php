<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $query = Supplier::withCount("purchaseOrders");

        if ($search = $request->search) {
            $query->where(fn ($q) => $q->where("name", "like", "%{$search}%")
                ->orWhere("phone", "like", "%{$search}%")
                ->orWhere("contact_person", "like", "%{$search}%"));
        }

        if ($request->boolean("inactive")) {
            $query->where("is_active", false);
        }

        $suppliers = $query->orderBy("name")->paginate(15)->withQueryString();

        return view("resources.suppliers.index", compact("suppliers"));
    }

    public function create()
    {
        return view("resources.suppliers.create");
    }

    public function store(Request $request)
    {
        Supplier::create($this->validated($request));

        return redirect()->route("resources.suppliers.index")
            ->with("success", __('app.supplier_created'));
    }

    public function show(Supplier $supplier)
    {
        $supplier->loadCount("purchaseOrders");
        $purchaseOrders = $supplier->purchaseOrders()->with("items")->latest("order_date")->latest("id")->limit(20)->get();

        return view("resources.suppliers.show", compact("supplier", "purchaseOrders"));
    }

    public function edit(Supplier $supplier)
    {
        return view("resources.suppliers.edit", compact("supplier"));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request));

        return redirect()->route("resources.suppliers.show", $supplier)
            ->with("success", __('app.supplier_updated'));
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return redirect()->route("resources.suppliers.show", $supplier)
                ->with("error", __('app.supplier_in_use'));
        }

        $supplier->delete();

        return redirect()->route("resources.suppliers.index")
            ->with("success", __('app.supplier_deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            "name" => "required|string|max:200",
            "contact_person" => "nullable|string|max:150",
            "phone" => "nullable|string|max:30",
            "email" => "nullable|email|max:150",
            "address" => "nullable|string|max:255",
            "city" => "nullable|string|max:100",
            "nit" => "nullable|string|max:50",
            "notes" => "nullable|string|max:2000",
            "is_active" => "nullable|boolean",
        ]);
    }
}
