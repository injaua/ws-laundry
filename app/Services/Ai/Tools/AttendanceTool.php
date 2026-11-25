<?php

namespace App\Services\Ai\Tools;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;

class AttendanceTool extends AiTool
{
    public function name(): string
    {
        return "hr_attendance";
    }

    public function description(): string
    {
        return "Staff and attendance: working employees per position, and in a period the attendance per status (present, absent, late, excused, vacation) "
            . "with the employees most absent or late.";
    }

    public function ability(): ?string
    {
        return "manage-hr";
    }

    protected function properties(): array
    {
        return [...self::periodProperties(), ...self::limitProperty()];
    }

    public function handle(User $user, array $input, ToolContext $context): array
    {
        [$from, $to] = self::period($input);
        $range = [$from->toDateString(), $to->toDateString()];

        $problems = Attendance::whereBetween("attendance_date", $range)->whereIn("status", ["absent", "late"])
            ->selectRaw("employee_id, SUM(status = 'absent') as absent, SUM(status = 'late') as late")
            ->groupBy("employee_id")->orderByRaw("SUM(status = 'absent') DESC, SUM(status = 'late') DESC")
            ->limit(self::limit($input))->toBase()->get();
        $names = Employee::whereIn("id", $problems->pluck("employee_id"))->pluck("name", "id");

        return [
            "period" => ["from" => $range[0], "to" => $range[1]],
            "working_employees" => Employee::working()->count(),
            "by_position" => Employee::working()->selectRaw("position, COUNT(*) as n")->groupBy("position")->pluck("n", "position")->all(),
            "attendance" => Attendance::whereBetween("attendance_date", $range)->selectRaw("status, COUNT(*) as n")->groupBy("status")->pluck("n", "status")->all(),
            "most_absent_or_late" => $problems->map(fn ($row) => [
                "employee" => $names[$row->employee_id] ?? null,
                "absent_days" => (int) $row->absent,
                "late_days" => (int) $row->late,
            ])->all(),
            "link" => route("attendance.index"),
        ];
    }
}
