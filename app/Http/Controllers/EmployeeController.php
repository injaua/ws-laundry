<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with("user");

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                    ->orWhere("position", "like", "%{$search}%");
            });
        }

        if ($status = $request->status) {
            $query->where("status", $status);
        }

        $employees = $query->orderBy("name")->paginate(15)->withQueryString();

        return view("employees.index", compact("employees"));
    }

    public function create()
    {
        $linkableUsers = User::inStore()->whereDoesntHave("employee")->orderBy("name")->get();

        return view("employees.create", compact("linkableUsers"));
    }

    public function store(Request $request)
    {
        $validated = $this->validateEmployee($request);
        $validated["created_by"] = $request->user()->id;

        Employee::create($validated);

        return redirect()->route("employees.index")
            ->with("success", __("app.employee_created"));
    }

    public function show(Employee $employee)
    {
        $employee->load(["user", "salaries" => fn ($q) => $q->latest("period_year")->latest("period_month")->limit(12),
            "vacations" => fn ($q) => $q->latest("start_date")->limit(10),
            "leaves" => fn ($q) => $q->latest("leave_date")->limit(10)]);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $summary = [
            "present" => Attendance::where("employee_id", $employee->id)
                ->whereBetween("attendance_date", [$monthStart, $monthEnd])
                ->whereIn("status", ["present", "late"])->count(),
            "absent" => Attendance::where("employee_id", $employee->id)
                ->whereBetween("attendance_date", [$monthStart, $monthEnd])
                ->where("status", "absent")->count(),
        ];

        return view("employees.show", compact("employee", "summary"));
    }

    public function edit(Employee $employee)
    {
        $linkableUsers = User::inStore()->where(function ($q) use ($employee) {
            $q->whereDoesntHave("employee")
                ->orWhere("id", $employee->user_id);
        })->orderBy("name")->get();

        return view("employees.edit", compact("employee", "linkableUsers"));
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $this->validateEmployee($request, $employee);

        $employee->update($validated);

        return redirect()->route("employees.show", $employee)
            ->with("success", __("app.employee_updated"));
    }

    public function updateStatus(Request $request, Employee $employee)
    {
        $allowed = match ($employee->status) {
            "probation" => ["active"],
            "active" => ["suspended"],
            "suspended" => ["active"],
            default => [],
        };

        $validated = $request->validate([
            "status" => "required|in:".implode(",", $allowed ?: ["__none__"]),
        ]);

        $employee->update(["status" => $validated["status"]]);

        return back()->with("success", __("app.employee_status_updated"));
    }

    public function terminate(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            "termination_date" => "required|date",
            "termination_reason" => "required|string|max:1000",
        ]);

        $employee->update($validated + ["status" => "terminated"]);

        return redirect()->route("employees.show", $employee)
            ->with("success", __("app.employee_terminated"));
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();

        return redirect()->route("employees.index")
            ->with("success", __("app.employee_deleted"));
    }

    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $validated = $request->validate([
            "name" => "required|string|max:100",
            "bi_number" => "nullable|string|max:30",
            "position" => "required|string|max:100",
            "phone" => "nullable|string|max:30",
            "address" => "nullable|string",
            "birth_date" => "nullable|date",
            "hire_date" => "required|date",
            "probation_ends_at" => "nullable|date",
            "contract_type" => "required|in:full_time,part_time,fixed_term",
            "base_salary" => "required|numeric|min:0",
            // Only an account of this store can be linked to the employee.
            "user_id" => ["nullable", Rule::exists("users", "id")->where("store_id", CurrentStore::id()), Rule::unique("employees", "user_id")->ignore($employee?->id)],
            "notes" => "nullable|string",
        ]);

        $validated["user_id"] = $validated["user_id"] ?? null;

        if (!$employee) {
            $validated["status"] = "probation";
        }

        return $validated;
    }
}
