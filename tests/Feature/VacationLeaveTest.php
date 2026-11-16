<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    public function test_vacation_working_days_are_calculated_on_store(): void
    {
        $employee = Employee::factory()->create();

        // Mon 16 .. Sun 22 Nov 2026 => 6 working days (Sundays excluded)
        $this->actingAs($this->owner())->post("/vacations", [
            "employee_id" => $employee->id,
            "start_date" => "2026-11-16",
            "end_date" => "2026-11-22",
            "type" => "vacation",
        ])->assertRedirect(route("vacations.index"));

        $this->assertDatabaseHas("vacations", [
            "employee_id" => $employee->id,
            "days" => 6,
            "status" => "pending",
        ]);
    }

    public function test_vacation_cannot_end_before_it_starts(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/vacations", [
            "employee_id" => $employee->id,
            "start_date" => "2026-11-20",
            "end_date" => "2026-11-16",
            "type" => "vacation",
        ])->assertSessionHasErrors("end_date");
    }

    public function test_vacation_approval_records_reviewer_and_status(): void
    {
        $owner = $this->owner();
        $vacation = Vacation::factory()->create();

        $this->actingAs($owner)
            ->patch("/vacations/{$vacation->id}/review", ["decision" => "approved"])
            ->assertSessionHas("success", __('app.vacation_reviewed'));

        $vacation->refresh();
        $this->assertSame("approved", $vacation->status);
        $this->assertSame($owner->id, $vacation->reviewed_by);
        $this->assertNotNull($vacation->reviewed_at);
    }

    public function test_vacation_can_be_rejected_and_deleted(): void
    {
        $vacation = Vacation::factory()->create();

        $this->actingAs($this->owner())
            ->patch("/vacations/{$vacation->id}/review", ["decision" => "rejected"]);
        $this->assertSame("rejected", $vacation->fresh()->status);

        $this->actingAs($this->owner())
            ->delete("/vacations/{$vacation->id}")
            ->assertRedirect(route("vacations.index"));
        $this->assertDatabaseMissing("vacations", ["id" => $vacation->id]);
    }

    public function test_dispensation_store_and_review(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/leaves", [
            "employee_id" => $employee->id,
            "leave_date" => "2026-11-17",
            "hours" => 4,
            "reason" => "Consulta médica",
        ])->assertRedirect(route("leaves.index"));

        $leave = Leave::firstWhere("employee_id", $employee->id);
        $this->assertNotNull($leave);
        $this->assertSame("pending", $leave->status);

        $owner = $this->owner();
        $this->actingAs($owner)
            ->patch("/leaves/{$leave->id}/review", ["decision" => "approved"]);

        $leave->refresh();
        $this->assertSame("approved", $leave->status);
        $this->assertSame($owner->id, $leave->reviewed_by);
    }

    public function test_dispensation_requires_reason(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/leaves", [
            "employee_id" => $employee->id,
            "leave_date" => now()->toDateString(),
            "hours" => 8,
        ])->assertSessionHasErrors("reason");
    }

    public function test_vacation_plan_paints_approved_and_pending_months(): void
    {
        $employee = Employee::factory()->create();
        Vacation::factory()->approved()->create([
            "employee_id" => $employee->id,
            "start_date" => "2026-08-10",
            "end_date" => "2026-08-20",
        ]);
        $pendingEmployee = Employee::factory()->create();
        Vacation::factory()->create([
            "employee_id" => $pendingEmployee->id,
            "start_date" => "2026-09-02",
            "end_date" => "2026-09-06",
        ]);

        $response = $this->actingAs($this->owner())->get("/vacations/plan?year=2026")
            ->assertOk();

        $response->assertSee($employee->name)
            ->assertSee($pendingEmployee->name)
            ->assertSee("11d")
            ->assertSee("table-success")
            ->assertSee("5d")
            ->assertSee("table-warning");
    }

    public function test_vacation_plan_splits_vacations_spanning_two_years(): void
    {
        $employee = Employee::factory()->create();
        Vacation::factory()->approved()->create([
            "employee_id" => $employee->id,
            "start_date" => "2025-12-20",
            "end_date" => "2026-01-05",
        ]);

        $this->actingAs($this->owner())->get("/vacations/plan?year=2026")
            ->assertOk()
            ->assertSee("5d");

        $this->actingAs($this->owner())->get("/vacations/plan?year=2025")
            ->assertOk()
            ->assertSee("12d");
    }

    public function test_vacation_plan_ignores_rejected_vacations(): void
    {
        $employee = Employee::factory()->create();
        $vacation = Vacation::factory()->create([
            "employee_id" => $employee->id,
            "start_date" => "2026-03-02",
            "end_date" => "2026-03-06",
        ]);
        $vacation->reject($this->owner());

        $this->actingAs($this->owner())->get("/vacations/plan?year=2026")
            ->assertOk()
            ->assertDontSee("table-warning");
    }

    public function test_cashier_cannot_access_vacation_plan(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/vacations/plan")->assertForbidden();
    }

    public function test_cashier_cannot_manage_vacations_or_leaves(): void
    {
        $cashier = User::factory()->cashier()->create();
        $vacation = Vacation::factory()->create();

        $this->actingAs($cashier)->get("/vacations")->assertForbidden();
        $this->actingAs($cashier)->get("/leaves")->assertForbidden();
        $this->actingAs($cashier)->patch("/vacations/{$vacation->id}/review", ["decision" => "approved"])->assertForbidden();
        $this->assertSame("pending", $vacation->fresh()->status);
    }
}
