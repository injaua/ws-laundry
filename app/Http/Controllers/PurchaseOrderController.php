<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryLot;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(["supplier", "items"]);

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        if ($supplierId = $request->supplier_id) {
            $query->where("supplier_id", $supplierId);
        }

        if ($search = $request->search) {
            $query->where("po_number", "like", "%{$search}%");
        }

        $orders = $query->latest("order_date")->latest("id")->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy("name")->get();

        return view("resources.purchases.index", compact("orders", "suppliers"));
    }

    public function create()
    {
        return view("resources.purchases.create", $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $order = DB::transaction(function () use ($request, $data) {
            $order = PurchaseOrder::draft([
                "supplier_id" => $data["supplier_id"],
                "user_id" => $request->user()->id,
                "order_date" => $data["order_date"],
                "expected_date" => $data["expected_date"] ?? null,
                "notes" => $data["notes"] ?? null,
            ]);

            $this->syncLines($order, $data["lines"]);

            return $order;
        });

        return redirect()->route("resources.purchases.show", $order)
            ->with("success", __('app.po_created'));
    }

    public function show(PurchaseOrder $purchase)
    {
        $purchase->load(["supplier", "user", "items.item"]);

        return view("resources.purchases.show", compact("purchase"));
    }

    public function edit(PurchaseOrder $purchase)
    {
        if (!$purchase->isEditable()) {
            return redirect()->route("resources.purchases.show", $purchase)
                ->with("error", __('app.po_locked'));
        }

        $purchase->load("items");

        return view("resources.purchases.edit", array_merge(compact("purchase"), $this->formData()));
    }

    public function update(Request $request, PurchaseOrder $purchase)
    {
        if (!$purchase->isEditable()) {
            return redirect()->route("resources.purchases.show", $purchase)
                ->with("error", __('app.po_locked'));
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($purchase, $data) {
            $purchase->update([
                "supplier_id" => $data["supplier_id"],
                "order_date" => $data["order_date"],
                "expected_date" => $data["expected_date"] ?? null,
                "notes" => $data["notes"] ?? null,
            ]);

            $purchase->items()->delete();
            $this->syncLines($purchase, $data["lines"]);
        });

        return redirect()->route("resources.purchases.show", $purchase)
            ->with("success", __('app.po_updated'));
    }

    public function order(PurchaseOrder $purchase)
    {
        $purchase->markOrdered();

        return redirect()->route("resources.purchases.show", $purchase)
            ->with("success", __('app.po_ordered'));
    }

    public function receive(Request $request, PurchaseOrder $purchase, PurchaseOrderItem $item)
    {
        $validated = $request->validate([
            "quantity" => "required|numeric|min:0.01",
            "unit_cost" => "nullable|numeric|min:0",
            "lot_number" => "nullable|string|max:60",
            "expires_at" => "nullable|date",
            "register_expense" => "nullable|boolean",
        ]);

        if ($item->purchase_order_id !== $purchase->id) {
            abort(404);
        }

        if (!empty($validated["lot_number"]) && InventoryLot::where("inventory_item_id", $item->inventory_item_id)
            ->where("lot_number", $validated["lot_number"])->exists()) {
            return back()->withInput()->withErrors(["lot_number" => __('app.lot_number_taken')]);
        }

        $purchase->receiveLine(
            $request->user(),
            $item,
            (float) $validated["quantity"],
            isset($validated["unit_cost"]) ? (int) $validated["unit_cost"] : null,
            ["lot_number" => $validated["lot_number"] ?? null, "expires_at" => $validated["expires_at"] ?? null],
            (bool) ($validated["register_expense"] ?? false),
        );

        return redirect()->route("resources.purchases.show", $purchase)
            ->with("success", __('app.po_line_received'));
    }

    public function cancel(PurchaseOrder $purchase)
    {
        $purchase->cancel();

        return redirect()->route("resources.purchases.show", $purchase)
            ->with("success", __('app.po_cancelled'));
    }

    public function destroy(PurchaseOrder $purchase)
    {
        if (InventoryMovement::where("source_type", "purchase_order")->where("source_id", $purchase->id)->exists()) {
            return redirect()->route("resources.purchases.show", $purchase)
                ->with("error", __('app.po_in_use'));
        }

        $purchase->delete();

        return redirect()->route("resources.purchases.index")
            ->with("success", __('app.po_deleted'));
    }

    private function syncLines(PurchaseOrder $order, array $lines): void
    {
        foreach ($lines as $line) {
            $cost = (int) round((float) $line["unit_cost"]);
            $order->items()->create([
                "inventory_item_id" => $line["inventory_item_id"],
                "quantity" => $line["quantity"],
                "unit_cost" => $cost,
                "subtotal" => (int) round((float) $line["quantity"] * $cost),
            ]);
        }

        $order->recalcTotal();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            "supplier_id" => "required|exists:suppliers,id",
            "order_date" => "required|date",
            "expected_date" => "nullable|date|after_or_equal:order_date",
            "notes" => "nullable|string|max:2000",
            "lines" => "required|array|min:1",
            "lines.*.inventory_item_id" => "required|exists:inventory_items,id",
            "lines.*.quantity" => "required|numeric|min:0.01",
            "lines.*.unit_cost" => "required|numeric|min:0",
        ]);

        $itemIds = array_column($data["lines"], "inventory_item_id");
        if (count($itemIds) !== count(array_unique($itemIds))) {
            throw ValidationException::withMessages([
                "lines" => __("app.po_duplicate_lines"),
            ]);
        }

        return $data;
    }

    private function formData(): array
    {
        return [
            "suppliers" => Supplier::where("is_active", true)->orderBy("name")->get(),
            "items" => InventoryItem::whereIn("kind", ["product", "material", "part"])
                ->where("is_active", true)
                ->orderBy("name")
                ->get(),
        ];
    }
}
