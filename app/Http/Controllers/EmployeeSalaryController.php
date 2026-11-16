<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Http\Request;

class EmployeeSalaryController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeSalary::with("employee");

        if ($year = $request->year) {
            $query->where("period_year", $year);
        }

        if ($month = $request->month) {
            $query->where("period_month", $month);
        }

        if ($employeeId = $request->employee_id) {
            $query->where("employee_id", $employeeId);
        }

        $salaries = $query->latest("period_year")->latest("period_month")
            ->paginate(15)->withQueryString();
        $employees = Employee::working()->orderBy("name")->get();

        return view("salaries.index", compact("salaries", "employees"));
    }

    public function summary(Request $request)
    {
        $month = max(1, min(12, (int) ($request->month ?: now()->month)));
        $year = (int) ($request->year ?: now()->year);

        $salaries = EmployeeSalary::with("employee")
            ->where("period_year", $year)
            ->where("period_month", $month)
            ->get()
            ->sortBy(fn (EmployeeSalary $salary) => mb_strtolower($salary->employee->name))
            ->values();

        $totalPaid = (int) $salaries->where("status", "paid")->sum("net_salary");
        $totalPending = (int) $salaries->where("status", "pending")->sum("net_salary");

        return view("salaries.summary", compact("salaries", "month", "year", "totalPaid", "totalPending"));
    }

    public function create()
    {
        $employees = Employee::working()->orderBy("name")->get();

        return view("salaries.create", compact("employees"));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayslip($request);

        if ($this->periodExists($validated)) {
            return back()->withInput()->withErrors([
                "period_month" => __("app.payslip_period_exists"),
            ]);
        }

        EmployeeSalary::create($validated);

        return redirect()->route("salaries.index")
            ->with("success", __("app.payslip_created"));
    }

    public function edit(EmployeeSalary $salary)
    {
        if ($salary->isPaid()) {
            return redirect()->route("salaries.index")
                ->with("error", __("app.cannot_edit_paid_payslip"));
        }

        $employees = Employee::working()->orderBy("name")->get();

        return view("salaries.edit", compact("salary", "employees"));
    }

    public function update(Request $request, EmployeeSalary $salary)
    {
        if ($salary->isPaid()) {
            return redirect()->route("salaries.index")
                ->with("error", __("app.cannot_edit_paid_payslip"));
        }

        $validated = $this->validatePayslip($request, $salary);

        if ($this->periodExists($validated, $salary)) {
            return back()->withInput()->withErrors([
                "period_month" => __("app.payslip_period_exists"),
            ]);
        }

        $salary->update($validated);

        return redirect()->route("salaries.index")
            ->with("success", __("app.payslip_updated"));
    }

    public function pay(EmployeeSalary $salary)
    {
        abort_if($salary->isPaid(), 404);

        $salary->markAsPaid();

        return back()->with("success", __("app.salary_paid"));
    }

    public function unpay(EmployeeSalary $salary)
    {
        abort_unless($salary->isPaid(), 404);

        $salary->unpay();

        return back()->with("success", __("app.salary_unpaid"));
    }

    public function destroy(EmployeeSalary $salary)
    {
        if ($salary->isPaid()) {
            return back()->with("error", __("app.cannot_delete_paid_payslip"));
        }

        $salary->delete();

        return redirect()->route("salaries.index")
            ->with("success", __("app.payslip_deleted"));
    }

    private function validatePayslip(Request $request, ?EmployeeSalary $salary = null): array
    {
        return $request->validate([
            "employee_id" => "required|exists:employees,id",
            "period_year" => "required|integer|min:2000|max:2100",
            "period_month" => "required|integer|min:1|max:12",
            "base_salary" => "required|numeric|min:0",
            "bonus" => "nullable|numeric|min:0",
            "deduction" => "nullable|numeric|min:0",
            "absence_deduction" => "nullable|numeric|min:0",
            "notes" => "nullable|string",
        ]);
    }

    public function absenceSuggestion(Request $request)
    {
        $validated = $request->validate([
            "employee_id" => "required|exists:employees,id",
            "period_year" => "required|integer|min:2000|max:2100",
            "period_month" => "required|integer|min:1|max:12",
        ]);

        $employee = Employee::findOrFail($validated["employee_id"]);

        return response()->json(
            EmployeeSalary::absenceDeductionSuggestion(
                $employee,
                (int) $validated["period_year"],
                (int) $validated["period_month"],
            )
        );
    }

    private function periodExists(array $validated, ?EmployeeSalary $salary = null): bool
    {
        return EmployeeSalary::where("employee_id", $validated["employee_id"])
            ->where("period_year", $validated["period_year"])
            ->where("period_month", $validated["period_month"])
            ->when($salary, fn ($q) => $q->whereKeyNot($salary->id))
            ->exists();
    }
}
