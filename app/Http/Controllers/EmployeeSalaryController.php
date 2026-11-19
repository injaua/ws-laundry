<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeSalaryRequest;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Support\Documents\DocumentResponse;
use App\Support\Documents\ReportDownload;
use App\Support\Documents\ReportTable;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeSalaryController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize("viewAny", EmployeeSalary::class);

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

    /** Payslip (recibo de vencimento): HTML, or PDF with ?format=pdf. */
    public function payslip(EmployeeSalary $salary)
    {
        Gate::authorize("viewAny", EmployeeSalary::class);

        return self::payslipResponse($salary);
    }

    public static function payslipResponse(EmployeeSalary $salary)
    {
        $salary->load("employee");

        return DocumentResponse::make(
            "documents.payslip",
            compact("salary"),
            __("app.payslip") . " " . $salary->employee->name . " " . sprintf("%04d-%02d", $salary->period_year, $salary->period_month),
        );
    }

    public function summary(Request $request)
    {
        Gate::authorize("viewAny", EmployeeSalary::class);

        $month = max(1, min(12, (int) ($request->month ?: now()->month)));
        $year = (int) ($request->year ?: now()->year);

        $salaries = EmployeeSalary::with("employee")
            ->where("period_year", $year)
            ->where("period_month", $month)
            ->get()
            ->sortBy(fn (EmployeeSalary $salary) => mb_strtolower($salary->employee->name))
            ->values();

        $totalPaid = Money::sum($salaries->where("status", "paid")->pluck("net_salary"));
        $totalPending = Money::sum($salaries->where("status", "pending")->pluck("net_salary"));

        if ($format = ReportDownload::requested($request)) {
            $table = new ReportTable(__("app.salary_summary"), sprintf("%02d/%04d", $month, $year), [
                [__("app.employee"), "text"], [__("app.base_salary"), "money"], [__("app.bonus"), "money"],
                [__("app.deduction"), "money"], [__("app.absence_deduction"), "money"], [__("app.net_salary"), "money"], [__("app.status"), "text"],
            ], __("app.salary_summary") . " " . sprintf("%04d-%02d", $year, $month));
            $table->summary(__("app.total_paid_salaries"), $totalPaid)->summary(__("app.total_pending_salaries"), $totalPending);
            foreach ($salaries as $salary) {
                $table->row([$salary->employee->name, $salary->base_salary, $salary->bonus, $salary->deduction, $salary->absence_deduction,
                    $salary->net_salary, $salary->isPaid() ? __("app.paid") : __("app.pending")]);
            }
            $table->row([__("app.month_total"), Money::sum($salaries->pluck("base_salary")), Money::sum($salaries->pluck("bonus")),
                Money::sum($salaries->pluck("deduction")), Money::sum($salaries->pluck("absence_deduction")), Money::sum($salaries->pluck("net_salary")), ""], "total");

            return ReportDownload::make($table, $format);
        }

        return view("salaries.summary", compact("salaries", "month", "year", "totalPaid", "totalPending"));
    }

    public function create()
    {
        Gate::authorize("create", EmployeeSalary::class);

        $employees = Employee::working()->orderBy("name")->get();

        return view("salaries.create", compact("employees"));
    }

    public function store(EmployeeSalaryRequest $request)
    {
        EmployeeSalary::create($request->validated());

        return redirect()->route("salaries.index")
            ->with("success", __("app.payslip_created"));
    }

    public function edit(EmployeeSalary $salary)
    {
        Gate::authorize("update", $salary);

        if ($salary->isPaid()) {
            return redirect()->route("salaries.index")
                ->with("error", __("app.cannot_edit_paid_payslip"));
        }

        $employees = Employee::working()->orderBy("name")->get();

        return view("salaries.edit", compact("salary", "employees"));
    }

    public function update(EmployeeSalaryRequest $request, EmployeeSalary $salary)
    {
        if ($salary->isPaid()) {
            return redirect()->route("salaries.index")
                ->with("error", __("app.cannot_edit_paid_payslip"));
        }

        $salary->update($request->validated());

        return redirect()->route("salaries.index")
            ->with("success", __("app.payslip_updated"));
    }

    public function pay(EmployeeSalary $salary)
    {
        Gate::authorize("pay", $salary);

        abort_if($salary->isPaid(), 404);

        $salary->markAsPaid();

        return back()->with("success", __("app.salary_paid"));
    }

    public function unpay(EmployeeSalary $salary)
    {
        Gate::authorize("pay", $salary);

        abort_unless($salary->isPaid(), 404);

        $salary->unpay();

        return back()->with("success", __("app.salary_unpaid"));
    }

    public function destroy(EmployeeSalary $salary)
    {
        Gate::authorize("delete", $salary);

        if ($salary->isPaid()) {
            return back()->with("error", __("app.cannot_delete_paid_payslip"));
        }

        $salary->delete();

        return redirect()->route("salaries.index")
            ->with("success", __("app.payslip_deleted"));
    }

    public function absenceSuggestion(Request $request)
    {
        Gate::authorize("create", EmployeeSalary::class);

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
}
