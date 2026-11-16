<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Vacation;
use Illuminate\Http\Request;

class VacationController extends Controller
{
    public function index(Request $request)
    {
        $query = Vacation::with("employee");

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        if ($employeeId = $request->employee_id) {
            $query->where("employee_id", $employeeId);
        }

        $vacations = $query->latest("start_date")->paginate(15)->withQueryString();
        $employees = Employee::working()->orderBy("name")->get();

        return view("vacations.index", compact("vacations", "employees"));
    }

    public function plan(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);

        $employees = Employee::working()->orderBy("name")->get();

        $vacations = Vacation::whereIn("status", ["pending", "approved"])
            ->where("start_date", "<=", "{$year}-12-31")
            ->where("end_date", ">=", "{$year}-01-01")
            ->get();

        $plan = [];
        foreach ($vacations as $vacation) {
            $cursor = $vacation->start_date->copy()->startOfMonth();
            while ($cursor->lessThanOrEqualTo($vacation->end_date)) {
                if ($cursor->year === $year) {
                    $month = (int) $cursor->format("n");
                    $from = max($vacation->start_date->timestamp, $cursor->timestamp);
                    $to = min($vacation->end_date->timestamp, $cursor->copy()->endOfMonth()->startOfDay()->timestamp);
                    $days = intdiv($to - $from, 86400) + 1;
                    $plan[$vacation->employee_id][$month][$vacation->status]
                        = ($plan[$vacation->employee_id][$month][$vacation->status] ?? 0) + $days;
                }
                $cursor->addMonth();
            }
        }

        return view("vacations.plan", compact("employees", "plan", "year"));
    }

    public function create()
    {
        $employees = Employee::working()->orderBy("name")->get();

        return view("vacations.create", compact("employees"));
    }

    public function store(Request $request)
    {
        $validated = $this->validateVacation($request);
        $validated["days"] = Vacation::workingDays($validated["start_date"], $validated["end_date"]);

        Vacation::create($validated);

        return redirect()->route("vacations.index")
            ->with("success", __("app.vacation_created"));
    }

    public function edit(Vacation $vacation)
    {
        $employees = Employee::working()->orderBy("name")->get();

        return view("vacations.edit", compact("vacation", "employees"));
    }

    public function update(Request $request, Vacation $vacation)
    {
        $validated = $this->validateVacation($request);
        $validated["days"] = Vacation::workingDays($validated["start_date"], $validated["end_date"]);

        $vacation->update($validated);

        return redirect()->route("vacations.index")
            ->with("success", __("app.vacation_updated"));
    }

    public function review(Request $request, Vacation $vacation)
    {
        $validated = $request->validate([
            "decision" => "required|in:approved,rejected",
        ]);

        if ($validated["decision"] === "approved") {
            $vacation->approve($request->user());
        } else {
            $vacation->reject($request->user());
        }

        return back()->with("success", __("app.vacation_reviewed"));
    }

    public function destroy(Vacation $vacation)
    {
        $vacation->delete();

        return redirect()->route("vacations.index")
            ->with("success", __("app.vacation_deleted"));
    }

    private function validateVacation(Request $request): array
    {
        return $request->validate([
            "employee_id" => "required|exists:employees,id",
            "start_date" => "required|date",
            "end_date" => "required|date|after_or_equal:start_date",
            "type" => "required|in:vacation,license",
            "status" => "nullable|in:pending,approved,rejected",
            "notes" => "nullable|string",
        ]);
    }
}
