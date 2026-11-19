<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::orderBy("name")->get();

        return view("expenses.categories.index", compact("categories"));
    }

    public function create()
    {
        $accounts = Account::where("is_group", false)->orderBy("code")->get();

        return view("expenses.categories.create", compact("accounts"));
    }

    public function store(Request $request)
    {
        $validated = $this->validateCategory($request);

        if ($this->salaryTaken($validated)) {
            return back()->withInput()->withErrors([
                "is_salary" => __("app.category_salary_only_one"),
            ]);
        }

        $validated["key"] = $this->uniqueKey($validated["name"]);

        ExpenseCategory::create($validated);

        return redirect()->route("expense-categories.index")
            ->with("success", __("app.category_created"));
    }

    public function edit(ExpenseCategory $expense_category)
    {
        $accounts = Account::where("is_group", false)->orderBy("code")->get();

        return view("expenses.categories.edit", [
            "category" => $expense_category,
            "accounts" => $accounts,
        ]);
    }

    public function update(Request $request, ExpenseCategory $expense_category)
    {
        $validated = $this->validateCategory($request);

        if ($this->salaryTaken($validated, $expense_category)) {
            return back()->withInput()->withErrors([
                "is_salary" => __("app.category_salary_only_one"),
            ]);
        }

        $expense_category->update($validated);

        return redirect()->route("expense-categories.index")
            ->with("success", __("app.category_updated"));
    }

    public function destroy(ExpenseCategory $expense_category)
    {
        if ($expense_category->expenseCount() > 0) {
            return redirect()->route("expense-categories.index")
                ->with("error", __("app.category_in_use"));
        }

        $expense_category->delete();

        return redirect()->route("expense-categories.index")
            ->with("success", __("app.category_deleted"));
    }

    private function validateCategory(Request $request): array
    {
        return $request->validate([
            "name" => "required|string|max:100",
            "color" => "required|in:".implode(",", ExpenseCategory::COLORS),
            "is_fixed" => "nullable|boolean",
            "default_amount" => "nullable|required_if:is_fixed,1|numeric|min:0",
            "is_salary" => "nullable|boolean",
            "is_vat_applicable" => "nullable|boolean",
            "account_id" => ["required", Rule::exists("accounts", "id")->where("is_group", false)],
        ]);
    }

    private function salaryTaken(array $validated, ?ExpenseCategory $except = null): bool
    {
        return (bool) ($validated["is_salary"] ?? false)
            && ExpenseCategory::where("is_salary", true)
                ->when($except, fn ($q) => $q->whereKeyNot($except->id))
                ->exists();
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: "categoria";
        $key = $base;
        $i = 2;
        while (ExpenseCategory::where("key", $key)->exists()) {
            $key = "{$base}-{$i}";
            $i++;
        }

        return $key;
    }
}
