<?php

namespace App\Http\Requests;

use App\Actions\Expenses\RaisePurchaseOrder;
use App\Http\Requests\Concerns\ValidatesAmounts;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared by expenses.store and expenses.update. On store the expense may also raise a
 * purchase order (create_purchase_order): a supplier and the lines bought, each naming a
 * resource by kind (cleaning product, material, part or equipment).
 */
class ExpenseRequest extends FormRequest
{
    use ValidatesAmounts;

    /** Most lines a purchase order raised from an expense may carry. */
    private const MAX_LINES = 50;

    public function authorize(): bool
    {
        return true; // role middleware on the route group decides who reaches this
    }

    public function rules(): array
    {
        return [
            "title" => "required|string|max:200",
            "description" => "nullable|string",
            "category" => "required|exists:expense_categories,key",
            "period_type" => "nullable|in:monthly,once",
            "amount" => "required|numeric|min:0",
            "vat_rate" => "nullable|numeric|min:0|max:100",
            "expense_date" => "required|date",
            "create_purchase_order" => "nullable|boolean",
            "po_supplier_id" => ["exclude_unless:create_purchase_order,1", "required", "integer", Rule::exists("suppliers", "id")],
            "po_lines" => ["exclude_unless:create_purchase_order,1", "required", "array", "min:1", "max:".self::MAX_LINES],
            "po_lines.*.kind" => ["exclude_unless:create_purchase_order,1", "required", Rule::in(RaisePurchaseOrder::KINDS)],
            "po_lines.*.name" => ["exclude_unless:create_purchase_order,1", "required", "string", "max:150"],
            "po_lines.*.unit" => ["exclude_unless:create_purchase_order,1", "nullable", Rule::in(InventoryItem::UNITS)],
            "po_lines.*.quantity" => ["exclude_unless:create_purchase_order,1", ...$this->quantityRules()],
            "po_lines.*.unit_cost" => ["exclude_unless:create_purchase_order,1", ...$this->amountRules()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (!$this->wantsPurchaseOrder()) {
                    return;
                }

                // Equipment is registered one asset per unit: whole quantities only.
                foreach ((array) $this->input("po_lines", []) as $i => $line) {
                    if (($line["kind"] ?? null) === "equipment" && is_numeric($line["quantity"] ?? null)
                        && floor((float) $line["quantity"]) != (float) $line["quantity"]) {
                        $validator->errors()->add("po_lines.{$i}.quantity", __("app.po_equipment_whole_qty"));
                    }
                }
            },
        ];
    }

    /** The expense also raises a purchase order (create only). */
    public function wantsPurchaseOrder(): bool
    {
        return $this->isMethod("post") && $this->boolean("create_purchase_order");
    }

    public function purchaseSupplier(): Supplier
    {
        return Supplier::findOrFail($this->validated()["po_supplier_id"]);
    }

    /** @return array<int, array{kind: string, name: string, unit: ?string, quantity: string, unit_cost: string}> */
    public function purchaseLines(): array
    {
        return array_values($this->validated()["po_lines"] ?? []);
    }

    /**
     * Validated data; only fixed categories carry a period type (monthly by default).
     * The VAT rate charged on the purchase defaults to the category's rule when omitted.
     */
    public function expenseData(): array
    {
        $data = collect($this->validated())->except(["create_purchase_order", "po_supplier_id", "po_lines"])->all();
        $category = ExpenseCategory::firstWhere("key", $data["category"]);
        $data["period_type"] = $category->is_fixed ? ($data["period_type"] ?? "monthly") : null;
        $data["vat_rate"] = isset($data["vat_rate"]) ? (float) $data["vat_rate"] : Expense::defaultVatRate($category);

        return $data;
    }
}
