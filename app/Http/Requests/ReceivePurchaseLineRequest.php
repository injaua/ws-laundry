<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAmounts;
use App\Models\InventoryLot;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Goods received on one purchase-order line: resources.purchases.receive.
 * The line must belong to the purchase order in the URL, and a lot number is
 * unique per stock item.
 */
class ReceivePurchaseLineRequest extends FormRequest
{
    use ValidatesAmounts;

    public function authorize(): bool
    {
        return true; // role middleware on the route group decides who reaches this
    }

    /** A line of another purchase order does not exist here. */
    protected function prepareForValidation(): void
    {
        abort_unless($this->line()->purchase_order_id === $this->purchase()->id, 404);
    }

    public function rules(): array
    {
        return [
            "quantity" => $this->quantityRules(),
            "unit_cost" => $this->amountRules(required: false),
            "lot_number" => ["nullable", "string", "max:60"],
            "expires_at" => ["nullable", "date"],
            "register_expense" => ["nullable", "boolean"],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $lot = trim((string) $this->input("lot_number"));
                if ($lot !== "" && InventoryLot::where("inventory_item_id", $this->line()->inventory_item_id)
                    ->where("lot_number", $lot)->exists()) {
                    $validator->errors()->add("lot_number", __("app.lot_number_taken"));
                }
            },
        ];
    }

    public function quantity(): float
    {
        return (float) $this->validated()["quantity"];
    }

    public function unitCost(): ?float
    {
        $cost = $this->validated()["unit_cost"] ?? null;

        return $cost !== null ? Money::round($cost) : null;
    }

    /** @return array{lot_number: ?string, expires_at: ?string} */
    public function lotData(): array
    {
        $data = $this->validated();

        return ["lot_number" => $data["lot_number"] ?? null, "expires_at" => $data["expires_at"] ?? null];
    }

    public function registersExpense(): bool
    {
        return (bool) ($this->validated()["register_expense"] ?? false);
    }

    public function purchase(): PurchaseOrder
    {
        return $this->route("purchase");
    }

    public function line(): PurchaseOrderItem
    {
        return $this->route("item");
    }
}
