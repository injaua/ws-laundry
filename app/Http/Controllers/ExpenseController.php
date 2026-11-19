<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\AccountingPeriod;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryMovement;
use App\Models\JournalEntry;
use App\Models\MaintenanceJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize("viewAny", Expense::class);

        $query = Expense::with("user");

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where("title", "like", "%{$search}%")
                    ->orWhere("description", "like", "%{$search}%");
            });
        }

        if ($category = $request->category) {
            $query->where("category", $category);
        }

        $expenses = $query->latest()->paginate(15);
        $categories = ExpenseCategory::orderBy("name")->get();

        return view("expenses.index", compact("expenses", "categories"));
    }

    public function create()
    {
        Gate::authorize("create", Expense::class);

        $categories = ExpenseCategory::orderBy("name")->get();

        return view("expenses.create", compact("categories"));
    }

    public function store(ExpenseRequest $request)
    {
        Gate::authorize("create", Expense::class);

        $validated = $request->expenseData();
        AccountingPeriod::assertOpen($validated["expense_date"], "expense_date");

        $validated["user_id"] = auth()->id();

        Expense::create($validated)->postToJournal();

        return redirect()->route("expenses.index")
            ->with("success", __('app.expense_recorded'));
    }

    public function edit(Expense $expense)
    {
        Gate::authorize("update", $expense);

        $categories = ExpenseCategory::orderBy("name")->get();

        return view("expenses.edit", compact("expense", "categories"));
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        Gate::authorize("update", $expense);

        $validated = $request->expenseData();
        AccountingPeriod::assertOpen($expense->expense_date, "expense_date");
        AccountingPeriod::assertOpen($validated["expense_date"], "expense_date");

        $expense->update($validated);

        $expense->postToJournal();

        return redirect()->route("expenses.index")
            ->with("success", __('app.expense_updated'));
    }

    public function destroy(Expense $expense)
    {
        Gate::authorize("delete", $expense);

        if ($period = AccountingPeriod::closedOn($expense->expense_date)) {
            return redirect()->route("expenses.index")
                ->with("error", __("app.period_closed", ["period" => $period->label()]));
        }

        // Drop pointers from the resources module so a deleted expense never leaves
        // a movement/job referencing a missing row.
        InventoryMovement::where("expense_id", $expense->id)->update(["expense_id" => null]);
        MaintenanceJob::where("expense_id", $expense->id)->update(["expense_id" => null]);

        $expense->delete();
        JournalEntry::forget("expense", $expense->id);

        return redirect()->route("expenses.index")
            ->with("success", __('app.expense_deleted'));
    }

    public function salaryTotal(Request $request)
    {
        Gate::authorize("create", Expense::class);

        $validated = $request->validate([
            "month" => "required|integer|min:1|max:12",
            "year" => "required|integer|min:2000|max:2100",
        ]);

        $amount = (int) EmployeeSalary::where("period_year", $validated["year"])
            ->where("period_month", $validated["month"])
            ->sum("net_salary");

        return response()->json(["amount" => $amount]);
    }
}
