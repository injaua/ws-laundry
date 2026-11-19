<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAmounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A purchase order and its lines: resources.purchases.store and .update.
 * Each stock item appears on one line only. Orders already sent to the supplier
 * are locked in the controller.
 */
class PurchaseOrderRequest extends FormRequest
{
    use ValidatesAmounts;

    /** Most lines one purchase order may carry. */
    private const MAX_LINES = 200;

    public function authorize(): bool
    {
        return true; // role middleware on the route group decides who reaches this
    }

    public function rules(): array
    {
        return [
            "supplier_id" => ["required", "integer", Rule::exists("suppliers", "id")],
            "order_date" => ["required", "date"],
            "expected_date" => ["nullable", "date", "after_or_equal:order_date"],
            "notes" => ["nullable", "string", "max:2000"],
            "lines" => ["required", "array", "min:1", "max:" . self::MAX_LINES],
            "lines.*.inventory_item_id" => ["required", "integer", Rule::exists("inventory_items", "id")],
            "lines.*.quantity" => $this->quantityRules(),
            "lines.*.unit_cost" => $this->amountRules(),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $itemIds = collect($this->input("lines", []))->pluck("inventory_item_id")->filter()->map(fn ($id) => (int) $id);
                if ($itemIds->count() !== $itemIds->unique()->count()) {
                    $validator->errors()->add("lines", __("app.po_duplicate_lines"));
                }
            },
        ];
    }

    /** Header fields of the purchase order. */
    public function headerData(): array
    {
        $data = $this->validated();

        return [
            "supplier_id" => $data["supplier_id"],
            "order_date" => $data["order_date"],
            "expected_date" => $data["expected_date"] ?? null,
            "notes" => $data["notes"] ?? null,
        ];
    }

    /** @return array<int, array{inventory_item_id: int, quantity: string, unit_cost: string}> */
    public function lines(): array
    {
        return $this->validated()["lines"];
    }
}
