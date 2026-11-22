<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\StoreOverview;
use App\Services\StoreProvisioner;
use App\Support\CurrentStore;
use App\Support\StoreReadiness;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Stores of the company (admin only): create and edit locations, switch the store worked
 * in, and compare every store side by side (company overview).
 */
class StoreController extends Controller
{
    public function index()
    {
        $stores = Store::withCount([
            "users as staff_count" => fn ($q) => $q->where("is_active", true),
            "owners",
        ])->orderBy("name")->get();

        // Payments / WhatsApp readiness of each active store (the configuration it really uses).
        $readiness = $stores->where("is_active", true)->mapWithKeys(fn (Store $s) => [$s->id => StoreReadiness::for($s->id)]);

        return view("admin.stores.index", ["stores" => $stores, "currentStoreId" => CurrentStore::id(), "readiness" => $readiness]);
    }

    public function create()
    {
        return view("admin.stores.create", [
            "store" => new Store(["is_active" => true]),
            "templates" => Store::orderBy("name")->get(),
        ]);
    }

    public function store(Request $request, StoreProvisioner $provisioner)
    {
        $data = $this->validated($request);
        $request->validate([
            "copy_from" => ["nullable", Rule::exists("stores", "id")],
            "copy_catalogue" => ["nullable", "boolean"],
        ]);

        $store = Store::create($data);
        $from = $request->filled("copy_from") ? Store::find($request->integer("copy_from")) : null;
        $provisioner->provision($store, $from, $request->boolean("copy_catalogue"));

        return redirect()->route("admin.stores.index")->with("success", __("app.store_created", ["name" => $store->name]));
    }

    public function edit(Store $store)
    {
        return view("admin.stores.edit", ["store" => $store]);
    }

    public function update(Request $request, Store $store)
    {
        $data = $this->validated($request, $store);

        if (!$data["is_active"] && $store->is_active && Store::active()->count() === 1) {
            return back()->with("error", __("app.store_last_active"));
        }

        $store->update($data);

        return redirect()->route("admin.stores.index")->with("success", __("app.store_updated"));
    }

    /** The admin's store switcher (top bar). */
    public function switch(Request $request, Store $store)
    {
        abort_unless($store->is_active, 404);
        CurrentStore::switchTo($store);

        $target = $request->input("to") === "reports" ? route("reports") : $this->backTo($request);

        return redirect()->to($target)->with("success", __("app.store_switched", ["name" => $store->name]));
    }

    /** Company overview: every store's key figures for a period, plus the totals. */
    public function overview(Request $request, StoreOverview $overview)
    {
        $request->validate([
            "from" => ["nullable", "date"],
            "to" => ["nullable", "date", "after_or_equal:from"],
        ]);
        $from = request_date("from", now()->startOfMonth());
        $to = request_date("to", now());

        return view("admin.stores.overview", $overview->build($from, $to) + ["from" => $from, "to" => $to]);
    }

    private function validated(Request $request, ?Store $store = null): array
    {
        $data = $request->validate([
            "name" => ["required", "string", "max:120"],
            "code" => ["required", "string", "max:20", "alpha_dash", Rule::unique("stores", "code")->ignore($store?->id)],
            "address" => ["nullable", "string", "max:255"],
            "phone" => ["nullable", "string", "max:30"],
            "email" => ["nullable", "email", "max:120"],
            "nuit" => ["nullable", "string", "max:20"],
        ]);
        $data["code"] = strtoupper($data["code"]);
        $data["is_active"] = $request->boolean("is_active");

        return $data;
    }

    /** Pages tied to a record of the previous store would 404 after the switch: go to the dashboard then. */
    private function backTo(Request $request): string
    {
        $previous = url()->previous();
        $path = parse_url($previous, PHP_URL_PATH) ?? "/";

        return preg_match("#/\d+(/|$)#", $path) ? route("dashboard") : $previous;
    }
}
