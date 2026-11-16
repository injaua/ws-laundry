<?php

namespace App\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $kind = $this->normalizeKind($request->kind);

        $query = InventoryItem::kind($kind)->withCount("movements");

        if ($search = $request->search) {
            $query->where("name", "like", "%{$search}%");
        }

        if ($request->boolean("low_stock")) {
            $query->where("min_stock_qty", ">", 0)->whereColumn("stock_qty", "<=", "min_stock_qty");
        }

        $items = $query->orderBy("name")->paginate(15)->withQueryString();

        return view("resources.items.index", compact("items", "kind"));
    }

    public function create(Request $request)
    {
        $kind = $this->normalizeKind($request->kind);

        return view("resources.items.create", compact("kind"));
    }

    public function store(Request $request)
    {
        $kind = $this->normalizeKind($request->kind);
        $validated = $this->validateItem($request, $kind);
        $validated["kind"] = $kind;
        $validated["min_stock_qty"] = $validated["min_stock_qty"] ?? 0;

        InventoryItem::create($validated);

        return redirect()->route("resources.items.index", ["kind" => $kind])
            ->with("success", __('app.item_created'));
    }

    public function show(InventoryItem $item)
    {
        $item->load(["movements" => fn ($q) => $q->with("user")->latest("moved_at")->latest("id")->limit(50)]);

        $jobParts = $item->kind === "part"
            ? $item->jobItems()->with("job.equipment")->latest()->limit(20)->get()
            : collect();

        $categories = ExpenseCategory::orderBy("name")->get();

        return view("resources.items.show", compact("item", "jobParts", "categories"));
    }

    public function edit(InventoryItem $item)
    {
        return view("resources.items.edit", compact("item"));
    }

    public function update(Request $request, InventoryItem $item)
    {
        $validated = $this->validateItem($request, $item->kind);
        $validated["min_stock_qty"] = $validated["min_stock_qty"] ?? 0;

        $item->update($validated);

        return redirect()->route("resources.items.show", $item)
            ->with("success", __('app.item_updated'));
    }

    public function destroy(InventoryItem $item)
    {
        if ($item->movements()->exists()) {
            return redirect()->route("resources.items.index", ["kind" => $item->kind])
                ->with("error", __('app.item_in_use'));
        }

        $kind = $item->kind;
        $item->delete();

        return redirect()->route("resources.items.index", ["kind" => $kind])
            ->with("success", __('app.item_deleted'));
    }

    private function normalizeKind(?string $kind): string
    {
        return in_array($kind, InventoryItem::KINDS, true) ? $kind : "product";
    }

    private function validateItem(Request $request, string $kind): array
    {
        return $request->validate([
            "name" => "required|string|max:200",
            "type" => ["nullable", Rule::in(InventoryItem::TYPES[$kind])],
            "unit" => "required|in:".implode(",", InventoryItem::UNITS),
            "min_stock_qty" => "nullable|numeric|min:0",
            "supplier" => "nullable|string|max:150",
            "is_active" => "nullable|boolean",
            "notes" => "nullable|string|max:2000",
        ]);
    }
}
