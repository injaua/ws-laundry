<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderRequest;
use App\Http\Requests\ReceivePurchaseLineRequest;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Support\Documents\DocumentResponse;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function store(PurchaseOrderRequest $request)
    {
        $order = DB::transaction(function () use ($request) {
            $order = PurchaseOrder::draft([
                ...$request->headerData(),
                "user_id" => $request->user()->id,
            ]);

            $this->syncLines($order, $request->lines());

            return $order;
        });

        return redirect()->route("resources.purchases.show", $order)
            ->with("success", __('app.po_created'));
    }

    /** Purchase order for the supplier (HTML, or PDF with ?format=pdf). */
    public function document(PurchaseOrder $purchase)
    {
        $purchase->load(["supplier", "user", "expense", "items.item", "items.equipment"]);

        return DocumentResponse::make("documents.purchase-order", compact("purchase"), __("app.purchase_order") . " " . $purchase->po_number);
    }

    public function show(PurchaseOrder $purchase)
    {
        $purchase->load(["supplier", "user", "expense", "items.item", "items.equipment"]);

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

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchase)
    {
        if (!$purchase->isEditable()) {
            return redirect()->route("resources.purchases.show", $purchase)
                ->with("error", __('app.po_locked'));
        }

        DB::transaction(function () use ($purchase, $request) {
            $purchase->update($request->headerData());

            $purchase->items()->delete();
            $this->syncLines($purchase, $request->lines());
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

    public function receive(ReceivePurchaseLineRequest $request, PurchaseOrder $purchase, PurchaseOrderItem $item)
    {
        $purchase->receiveLine(
            $request->user(),
            $item,
            $request->quantity(),
            $request->unitCost(),
            $request->lotData(),
            $request->registersExpense(),
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
            $cost = Money::round($line["unit_cost"]);
            $order->items()->create([
                "inventory_item_id" => $line["inventory_item_id"],
                "quantity" => $line["quantity"],
                "unit_cost" => $cost,
                "subtotal" => Money::mul($cost, $line["quantity"]),
            ]);
        }

        $order->recalcTotal();
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
