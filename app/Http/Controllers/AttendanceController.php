<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $this->resolveDate($request);

        $employees = Employee::working()->orderBy("name")->get();

        $registered = Attendance::with("employee")
            ->where("attendance_date", $date)
            ->get()
            ->keyBy("employee_id");

        // Pre-fill defaults from approved vacations / excused leaves
        $defaults = [];
        foreach ($employees as $employee) {
            if ($registered->has($employee->id)) {
                continue;
            }
            $onVacation = $employee->vacations
                ->first(fn ($v) => $v->coversDate($date));
            if ($onVacation) {
                $defaults[$employee->id] = "vacation";

                continue;
            }
            $excused = $employee->leaves
                ->first(fn ($l) => $l->status === "approved"
                    && $l->leave_date->toDateString() === $date);
            if ($excused) {
                $defaults[$employee->id] = "excused";
            }
        }

        return view("attendance.index", compact("date", "employees", "registered", "defaults"));
    }

    public function summary(Request $request)
    {
        [$summaryStart, $summaryEnd] = $this->resolveSummaryPeriod($request);

        $startDate = Carbon::parse($summaryStart);
        $summaryMonth = $startDate->day === 1 && $summaryEnd === $startDate->copy()->endOfMonth()->toDateString()
            ? $startDate->format("Y-m")
            : "";

        $summary = Attendance::query()
            ->selectRaw("employee_id")
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS days_present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS days_absent")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS days_late")
            ->selectRaw("SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) AS days_excused")
            ->selectRaw("SUM(CASE WHEN status = 'vacation' THEN 1 ELSE 0 END) AS days_vacation")
            ->selectRaw("COUNT(*) AS days_total")
            ->whereBetween("attendance_date", [$summaryStart, $summaryEnd])
            ->groupBy("employee_id")
            ->get();

        $names = Employee::whereIn("id", $summary->pluck("employee_id"))->pluck("name", "id");

        $summary = $summary
            ->map(function ($row) {
                $expected = $row->days_present + $row->days_absent + $row->days_late;
                $row->attendance_rate = $expected > 0
                    ? round(($row->days_present + $row->days_late) * 100 / $expected, 1)
                    : null;

                return $row;
            })
            ->sortBy(fn ($row) => $names[$row->employee_id] ?? "")
            ->values();

        return view("attendance.summary", compact("summary", "summaryStart", "summaryEnd", "summaryMonth", "names"));
    }

    public function daily(Request $request)
    {
        $validated = $request->validate([
            "days" => "nullable|integer|in:7,15,30,90",
            "employee_id" => "nullable|integer",
            "date" => "nullable|date",
        ]);

        $days = (int) ($validated["days"] ?? 30);
        $endDate = isset($validated["date"])
            ? Carbon::parse($validated["date"])->toDateString()
            : now()->toDateString();
        $startDate = Carbon::parse($endDate)->subDays($days - 1)->toDateString();
        $employeeId = $validated["employee_id"] ?? "";

        $records = Attendance::whereBetween("attendance_date", [$startDate, $endDate])
            ->when($employeeId, fn ($q) => $q->where("employee_id", $employeeId))
            ->get(["employee_id", "attendance_date", "status"]);

        $byEmployee = $records
            ->groupBy("employee_id")
            ->map(fn ($group) => $group->mapWithKeys(
                fn ($r) => [$r->attendance_date->toDateString() => $r->status]
            ));

        $allEmployees = Employee::working()->get();
        // Include employees terminated during the window that still have records
        $missing = $records->pluck("employee_id")->unique()->diff($allEmployees->pluck("id"));
        if ($missing->isNotEmpty()) {
            $allEmployees = $allEmployees->concat(Employee::whereIn("id", $missing)->get());
        }
        $allEmployees = $allEmployees->sortBy("name")->values();

        $employees = $employeeId
            ? $allEmployees->where("id", (int) $employeeId)->values()
            : $allEmployees;

        $dates = [];
        for ($d = Carbon::parse($startDate); $d->lte(Carbon::parse($endDate)); $d->addDay()) {
            $dates[] = $d->toDateString();
        }

        $selectedEmployee = $employeeId ? $employees->firstWhere("id", (int) $employeeId) : null;
        $selectedStatus = $selectedEmployee
            ? ($byEmployee->get($selectedEmployee->id)[$endDate] ?? null)
            : null;

        return view("attendance.daily", compact(
            "employees", "allEmployees", "byEmployee", "dates", "days", "endDate", "employeeId",
            "selectedEmployee", "selectedStatus"
        ));
    }

    public function storeBatch(Request $request)
    {
        $validated = $request->validate([
            "date" => "required|date",
            "attendance" => "nullable|array",
            "attendance.*" => "nullable|in:".implode(",", Attendance::STATUSES),
        ]);

        $date = $validated["date"];
        $workingIds = Employee::working()->pluck("id");
        $saved = 0;

        foreach ($validated["attendance"] ?? [] as $employeeId => $status) {
            if (! $workingIds->contains($employeeId)) {
                continue;
            }

            if ($status === null || $status === "") {
                Attendance::where("employee_id", $employeeId)
                    ->where("attendance_date", $date)
                    ->delete();

                continue;
            }

            Attendance::updateOrCreate(
                ["employee_id" => $employeeId, "attendance_date" => $date],
                ["status" => $status, "registered_by" => $request->user()->id],
            );
            $saved++;
        }

        return redirect()->route("attendance.index", ["date" => $date])
            ->with("success", __("app.attendance_saved"));
    }

    public function destroy(Request $request, Attendance $attendance)
    {
        $date = $attendance->attendance_date->toDateString();
        $attendance->delete();

        return redirect()->route("attendance.index", ["date" => $date])
            ->with("success", __("app.attendance_saved"));
    }

    /**
     * Summary period: month (YYYY-MM) wins, then explicit range, else current month.
     *
     * @return array{string, string} [start, end] as Y-m-d
     */
    private function resolveSummaryPeriod(Request $request): array
    {
        $validated = $request->validate([
            "summary_month" => "nullable|string|max:7",
            "summary_start" => "nullable|date",
            "summary_end" => "nullable|date",
        ]);

        if (! empty($validated["summary_month"])
            && preg_match("/^(\d{4})-(0[1-9]|1[0-2])$/", $validated["summary_month"], $m)) {
            $month = Carbon::create((int) $m[1], (int) $m[2], 1);

            return [$month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString()];
        }

        if (! empty($validated["summary_start"]) && ! empty($validated["summary_end"])) {
            $start = Carbon::parse($validated["summary_start"])->toDateString();
            $end = Carbon::parse($validated["summary_end"])->toDateString();

            return $start <= $end ? [$start, $end] : [$end, $start];
        }

        return [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
    }

    private function resolveDate(Request $request): string
    {
        $validated = $request->validate([
            "date" => "nullable|date",
        ]);

        return isset($validated["date"])
            ? Carbon::parse($validated["date"])->toDateString()
            : now()->toDateString();
    }
}
