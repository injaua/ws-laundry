<?php

namespace App\Http\Requests;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

/** Shared by expenses.store and expenses.update. */
class ExpenseRequest extends FormRequest
{
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
            "expense_date" => "required|date",
        ];
    }

    /** Validated data; only fixed categories carry a period type (monthly by default). */
    public function expenseData(): array
    {
        $data = $this->validated();
        $category = ExpenseCategory::firstWhere("key", $data["category"]);
        $data["period_type"] = $category->is_fixed ? ($data["period_type"] ?? "monthly") : null;

        return $data;
    }
}
