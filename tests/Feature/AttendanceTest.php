<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    public function test_bulk_roll_call_registers_multiple_employees(): void
    {
        $a = Employee::factory()->create();
        $b = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/attendance", [
            "date" => "2026-11-15",
            "attendance" => [
                $a->id => "present",
                $b->id => "absent",
            ],
        ])->assertRedirect(route("attendance.index", ["date" => "2026-11-15"]));

        $this->assertDatabaseHas("attendances", [
            "employee_id" => $a->id, "attendance_date" => "2026-11-15", "status" => "present",
        ]);
        $this->assertDatabaseHas("attendances", [
            "employee_id" => $b->id, "attendance_date" => "2026-11-15", "status" => "absent",
        ]);
    }

    public function test_re_registering_same_day_updates_existing_record(): void
    {
        $employee = Employee::factory()->create();
        $date = "2026-11-15";

        foreach (["present", "late"] as $status) {
            $this->actingAs($this->owner())->post("/attendance", [
                "date" => $date,
                "attendance" => [$employee->id => $status],
            ]);
        }

        $this->assertSame(1, Attendance::where("employee_id", $employee->id)->count());
        $this->assertDatabaseHas("attendances", [
            "employee_id" => $employee->id, "attendance_date" => $date, "status" => "late",
        ]);
    }

    public function test_blank_entry_removes_existing_record(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create([
            "employee_id" => $employee->id,
            "attendance_date" => "2026-11-15",
            "status" => "present",
        ]);

        $this->actingAs($this->owner())->post("/attendance", [
            "date" => "2026-11-15",
            "attendance" => [$employee->id => ""],
        ]);

        $this->assertDatabaseMissing("attendances", ["employee_id" => $employee->id]);
    }

    public function test_terminated_employees_are_ignored_in_batch(): void
    {
        $employee = Employee::factory()->terminated()->create();

        $this->actingAs($this->owner())->post("/attendance", [
            "date" => "2026-11-15",
            "attendance" => [$employee->id => "present"],
        ]);

        $this->assertDatabaseCount("attendances", 0);
    }

    public function test_roll_call_page_prefills_vacation_and_excused_defaults(): void
    {
        $employee = Employee::factory()->create();
        Vacation::factory()->approved()->create([
            "employee_id" => $employee->id,
            "start_date" => "2026-11-15",
            "end_date" => "2026-11-20",
        ]);

        $this->actingAs($this->owner())->get("/attendance?date=2026-11-16")
            ->assertOk()
            ->assertSee(__('app.on_vacation'), false);
    }

    public function test_cashier_cannot_access_attendance_management(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/attendance")->assertForbidden();
        $this->actingAs($cashier)->get("/attendance/summary")->assertForbidden();
        $this->actingAs($cashier)->get("/attendance/daily")->assertForbidden();
        $this->actingAs($cashier)->post("/attendance", ["date" => "2026-11-15"])->assertForbidden();
    }

    public function test_summary_defaults_to_current_month(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create([
            "employee_id" => $employee->id,
            "attendance_date" => now()->startOfMonth()->addDays(2)->toDateString(),
            "status" => "present",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id,
            "attendance_date" => now()->toDateString(),
            "status" => "absent",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id,
            "attendance_date" => now()->subMonth()->toDateString(),
            "status" => "absent",
        ]);

        $this->actingAs($this->owner())->get("/attendance/summary")
            ->assertOk()
            ->assertViewHas("summary", function ($summary) use ($employee) {
                return $summary->count() === 1
                    && $summary[0]->employee_id === $employee->id
                    && $summary[0]->days_present == 1
                    && $summary[0]->days_absent == 1
                    && $summary[0]->days_total == 2
                    && $summary[0]->attendance_rate === 50.0;
            });
    }

    public function test_summary_month_filter_isolates_single_month(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-10-10", "status" => "present",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-11-10", "status" => "absent",
        ]);

        $this->actingAs($this->owner())->get("/attendance/summary?summary_month=2026-10")
            ->assertOk()
            ->assertViewHas("summaryMonth", "2026-10")
            ->assertViewHas("summary", function ($summary) {
                return $summary->count() === 1
                    && $summary[0]->days_present == 1
                    && $summary[0]->days_absent == 0
                    && $summary[0]->days_total == 1
                    && $summary[0]->attendance_rate === 100.0;
            });
    }

    public function test_summary_period_filter_spans_months(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-10-20", "status" => "present",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-11-05", "status" => "late",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-12-01", "status" => "absent",
        ]);

        $this->actingAs($this->owner())->get("/attendance/summary?summary_start=2026-10-20&summary_end=2026-11-30")
            ->assertOk()
            ->assertViewHas("summary", function ($summary) {
                return $summary->count() === 1
                    && $summary[0]->days_present == 1
                    && $summary[0]->days_late == 1
                    && $summary[0]->days_total == 2
                    && $summary[0]->attendance_rate === 100.0;
            })
            ->assertViewHas("summaryMonth", "");
    }

    public function test_daily_matrix_shows_status_per_employee_and_day(): void
    {
        $a = Employee::factory()->create(["name" => "Alpha"]);
        $b = Employee::factory()->create(["name" => "Bravo"]);
        Attendance::factory()->create([
            "employee_id" => $a->id, "attendance_date" => now()->toDateString(), "status" => "present",
        ]);
        Attendance::factory()->create([
            "employee_id" => $a->id, "attendance_date" => now()->subDays(5)->toDateString(), "status" => "absent",
        ]);
        Attendance::factory()->create([
            "employee_id" => $b->id, "attendance_date" => now()->subDays(2)->toDateString(), "status" => "late",
        ]);

        $this->actingAs($this->owner())->get("/attendance/daily")
            ->assertOk()
            ->assertViewHas("dates", fn ($dates) => count($dates) === 30 && end($dates) === now()->toDateString())
            ->assertViewHas("employees", fn ($employees) => $employees->pluck("name")->all() === ["Alpha", "Bravo"])
            ->assertViewHas("byEmployee", function ($byEmployee) use ($a, $b) {
                return $byEmployee->get($a->id)[now()->toDateString()] === "present"
                    && $byEmployee->get($a->id)[now()->subDays(5)->toDateString()] === "absent"
                    && $byEmployee->get($b->id)[now()->subDays(2)->toDateString()] === "late";
            })
            ->assertSee(__('app.legend'), false);
    }

    public function test_daily_filters_single_employee_on_chosen_day(): void
    {
        $a = Employee::factory()->create();
        $b = Employee::factory()->create();
        Attendance::factory()->create([
            "employee_id" => $a->id, "attendance_date" => "2026-10-10", "status" => "vacation",
        ]);
        Attendance::factory()->create([
            "employee_id" => $b->id, "attendance_date" => "2026-10-10", "status" => "present",
        ]);

        $this->actingAs($this->owner())->get("/attendance/daily?employee_id={$a->id}&date=2026-10-10")
            ->assertOk()
            ->assertViewHas("endDate", "2026-10-10")
            ->assertViewHas("employeeId", $a->id)
            ->assertViewHas("employees", fn ($employees) => $employees->count() === 1 && $employees[0]->id === $a->id)
            ->assertViewHas("byEmployee", fn ($by) => $by->count() === 1)
            ->assertViewHas("selectedStatus", "vacation")
            ->assertSee(__('app.on_vacation'), false);
    }

    public function test_daily_window_respects_days_and_excludes_older_records(): void
    {
        $employee = Employee::factory()->create();
        $recent = now()->subDays(3)->toDateString();
        $old = now()->subDays(20)->toDateString();
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => $recent, "status" => "present",
        ]);
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => $old, "status" => "absent",
        ]);

        $this->actingAs($this->owner())->get("/attendance/daily?days=7")
            ->assertOk()
            ->assertViewHas("dates", fn ($dates) => count($dates) === 7)
            ->assertViewHas("byEmployee", function ($by) use ($employee, $recent, $old) {
                $row = $by->get($employee->id);

                return $row !== null
                    && $row[$recent] === "present"
                    && !isset($row[$old]);
            });
    }
}
