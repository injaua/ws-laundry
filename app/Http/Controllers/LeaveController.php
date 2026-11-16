<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $query = Leave::with("employee");

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        if ($employeeId = $request->employee_id) {
            $query->where("employee_id", $employeeId);
        }

        $leaves = $query->latest("leave_date")->paginate(15)->withQueryString();
        $employees = Employee::working()->orderBy("name")->get();

        return view("leaves.index", compact("leaves", "employees"));
    }

    public function create()
    {
        $employees = Employee::working()->orderBy("name")->get();

        return view("leaves.create", compact("employees"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "employee_id" => "required|exists:employees,id",
            "leave_date" => "required|date",
            "hours" => "required|integer|min:1|max:24",
            "reason" => "required|string|max:255",
        ]);
        $validated["status"] = "pending";

        Leave::create($validated);

        return redirect()->route("leaves.index")
            ->with("success", __("app.dispensation_created"));
    }

    public function review(Request $request, Leave $leave)
    {
        $validated = $request->validate([
            "decision" => "required|in:approved,rejected",
        ]);

        if ($validated["decision"] === "approved") {
            $leave->approve($request->user());
        } else {
            $leave->reject($request->user());
        }

        return back()->with("success", __("app.dispensation_reviewed"));
    }

    public function destroy(Leave $leave)
    {
        $leave->delete();

        return redirect()->route("leaves.index")
            ->with("success", __("app.dispensation_deleted"));
    }
}
