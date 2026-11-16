<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;

class MyHrController extends Controller
{
    public function payslips(Request $request)
    {
        $employee = $this->employeeFor($request);

        if (! $employee) {
            return view("hr.payslips", ["employee" => null, "salaries" => collect()]);
        }

        $salaries = $employee->salaries()
            ->latest("period_year")->latest("period_month")
            ->paginate(15);

        return view("hr.payslips", compact("employee", "salaries"));
    }

    public function attendance(Request $request)
    {
        $employee = $this->employeeFor($request);

        if (! $employee) {
            return view("hr.attendance", [
                "employee" => null,
                "month" => now()->format("Y-m"),
                "attendances" => collect(),
                "summary" => ["present" => 0, "absent" => 0],
            ]);
        }

        $request->validate(["month" => "nullable|date_format:Y-m"]);
        $month = $request->query("month", now()->format("Y-m"));

        $attendances = Attendance::where("employee_id", $employee->id)
            ->whereYear("attendance_date", substr($month, 0, 4))
            ->whereMonth("attendance_date", (int) substr($month, 5, 2))
            ->orderBy("attendance_date")
            ->get();

        $summary = [
            "present" => $attendances->whereIn("status", ["present", "late"])->count(),
            "absent" => $attendances->where("status", "absent")->count(),
        ];

        return view("hr.attendance", compact("employee", "month", "attendances", "summary"));
    }

    private function employeeFor(Request $request): ?Employee
    {
        return Employee::where("user_id", $request->user()->id)->first();
    }
}
