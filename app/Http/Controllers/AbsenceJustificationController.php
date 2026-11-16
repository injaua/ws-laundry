<?php

namespace App\Http\Controllers;

use App\Models\AbsenceJustification;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsenceJustificationController extends Controller
{
    public function index(Request $request)
    {
        $query = AbsenceJustification::with("employee");

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        if ($employeeId = $request->employee_id) {
            $query->where("employee_id", $employeeId);
        }

        $justifications = $query->latest("absence_date")->paginate(15)->withQueryString();

        // Absences still without any justification (pending, approved or rejected)
        $unjustified = Attendance::with("employee")
            ->where("status", "absent")
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from("absence_justifications")
                    ->whereColumn("absence_justifications.employee_id", "attendances.employee_id")
                    ->whereColumn("absence_justifications.absence_date", "attendances.attendance_date");
            })
            ->latest("attendance_date")
            ->limit(10)
            ->get();

        $employees = Employee::working()->orderBy("name")->get();

        return view("justifications.index", compact("justifications", "unjustified", "employees"));
    }

    public function create(Request $request)
    {
        $employees = Employee::working()->orderBy("name")->get();
        $prefill = [
            "employee_id" => $request->integer("employee_id") ?: "",
            "date" => $request->filled("date") ? $request->date("date")->toDateString() : now()->toDateString(),
        ];

        return view("justifications.create", compact("employees", "prefill"));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "employee_id" => "required|exists:employees,id",
            "absence_date" => "required|date",
            "reason" => "required|string|max:255",
            "notes" => "nullable|string",
        ]);

        if (AbsenceJustification::where("employee_id", $validated["employee_id"])
            ->where("absence_date", $validated["absence_date"])->exists()) {
            return back()->withInput()->withErrors([
                "absence_date" => __("app.justification_exists"),
            ]);
        }

        if (! Attendance::where("employee_id", $validated["employee_id"])
            ->where("attendance_date", $validated["absence_date"])
            ->where("status", "absent")->exists()) {
            return back()->withInput()->withErrors([
                "absence_date" => __("app.no_absence_to_justify"),
            ]);
        }

        AbsenceJustification::create($validated);

        return redirect()->route("justifications.index")
            ->with("success", __("app.justification_created"));
    }

    public function review(Request $request, AbsenceJustification $justification)
    {
        $validated = $request->validate([
            "decision" => "required|in:approved,rejected",
        ]);

        if ($validated["decision"] === "approved") {
            $justification->approve($request->user());
        } else {
            $justification->reject($request->user());
        }

        return back()->with("success", __("app.justification_reviewed"));
    }

    public function destroy(AbsenceJustification $justification)
    {
        if ($justification->status === "approved") {
            $attendance = Attendance::where("employee_id", $justification->employee_id)
                ->where("attendance_date", $justification->absence_date->toDateString())
                ->where("status", "excused")
                ->where("notes", $justification->reason)
                ->first();
            $attendance?->update(["status" => "absent", "notes" => null]);
        }

        $justification->delete();

        return redirect()->route("justifications.index")
            ->with("success", __("app.justification_deleted"));
    }
}
