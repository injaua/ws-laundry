<?php

namespace Tests\Feature;

use App\Models\AbsenceJustification;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceJustificationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner()->create();
    }

    private function absence(Employee $employee, string $date = "2026-11-10"): Attendance
    {
        return Attendance::factory()->create([
            "employee_id" => $employee->id,
            "attendance_date" => $date,
            "status" => "absent",
        ]);
    }

    public function test_store_creates_pending_justification_for_recorded_absence(): void
    {
        $employee = Employee::factory()->create();
        $this->absence($employee);

        $this->actingAs($this->owner())->post("/justifications", [
            "employee_id" => $employee->id,
            "absence_date" => "2026-11-10",
            "reason" => "Doença",
        ])->assertRedirect(route("justifications.index"));

        $this->assertDatabaseHas("absence_justifications", [
            "employee_id" => $employee->id, "absence_date" => "2026-11-10",
            "status" => "pending", "reason" => "Doença",
        ]);
    }

    public function test_store_requires_a_recorded_absence_on_that_day(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->owner())->post("/justifications", [
            "employee_id" => $employee->id,
            "absence_date" => "2026-11-10",
            "reason" => "Doença",
        ])->assertSessionHasErrors("absence_date");

        $this->assertDatabaseCount("absence_justifications", 0);
    }

    public function test_duplicate_justification_for_same_day_is_rejected(): void
    {
        $employee = Employee::factory()->create();
        $this->absence($employee);
        AbsenceJustification::factory()->create([
            "employee_id" => $employee->id, "absence_date" => "2026-11-10",
        ]);

        $this->actingAs($this->owner())->post("/justifications", [
            "employee_id" => $employee->id,
            "absence_date" => "2026-11-10",
            "reason" => "Outro motivo",
        ])->assertSessionHasErrors("absence_date");

        $this->assertDatabaseCount("absence_justifications", 1);
    }

    public function test_approval_converts_the_absence_into_excused(): void
    {
        $employee = Employee::factory()->create();
        $this->absence($employee);
        $justification = AbsenceJustification::factory()->create([
            "employee_id" => $employee->id,
            "absence_date" => "2026-11-10",
            "reason" => "Doença",
        ]);
        $owner = $this->owner();

        $this->actingAs($owner)->patch("/justifications/{$justification->id}/review", [
            "decision" => "approved",
        ])->assertRedirect();

        $this->assertDatabaseHas("attendances", [
            "employee_id" => $employee->id, "attendance_date" => "2026-11-10",
            "status" => "excused", "notes" => "Doença",
        ]);
        $this->assertDatabaseHas("absence_justifications", [
            "id" => $justification->id, "status" => "approved", "reviewed_by" => $owner->id,
        ]);
    }

    public function test_rejection_keeps_the_absence_unjustified(): void
    {
        $employee = Employee::factory()->create();
        $this->absence($employee);
        $justification = AbsenceJustification::factory()->create([
            "employee_id" => $employee->id, "absence_date" => "2026-11-10",
        ]);

        $this->actingAs($this->owner())->patch("/justifications/{$justification->id}/review", [
            "decision" => "rejected",
        ]);

        $this->assertDatabaseHas("attendances", [
            "employee_id" => $employee->id, "attendance_date" => "2026-11-10", "status" => "absent",
        ]);
        $this->assertDatabaseHas("absence_justifications", [
            "id" => $justification->id, "status" => "rejected",
        ]);
    }

    public function test_deleting_an_approved_justification_reverts_the_day_to_absent(): void
    {
        $employee = Employee::factory()->create();
        Attendance::factory()->create([
            "employee_id" => $employee->id, "attendance_date" => "2026-11-10",
            "status" => "excused", "notes" => "Doença",
        ]);
        $justification = AbsenceJustification::factory()->create([
            "employee_id" => $employee->id,
            "absence_date" => "2026-11-10",
            "reason" => "Doença",
            "status" => "approved",
        ]);

        $this->actingAs($this->owner())->delete("/justifications/{$justification->id}")
            ->assertRedirect(route("justifications.index"));

        $this->assertDatabaseHas("attendances", [
            "employee_id" => $employee->id, "attendance_date" => "2026-11-10",
            "status" => "absent", "notes" => null,
        ]);
    }

    public function test_index_lists_absences_still_without_justification(): void
    {
        $a = Employee::factory()->create(["name" => "Alpha"]);
        $b = Employee::factory()->create(["name" => "Bravo"]);
        $this->absence($a, "2026-11-10");
        $this->absence($b, "2026-11-11");
        AbsenceJustification::factory()->create([
            "employee_id" => $b->id, "absence_date" => "2026-11-11",
        ]);

        $this->actingAs($this->owner())->get("/justifications")
            ->assertOk()
            ->assertViewHas("unjustified", fn ($list) => $list->count() === 1 && $list[0]->employee_id === $a->id)
            ->assertSee("Alpha");
    }

    public function test_cashier_cannot_manage_justifications(): void
    {
        $cashier = User::factory()->cashier()->create();
        $employee = Employee::factory()->create();
        $this->absence($employee);
        $justification = AbsenceJustification::factory()->create([
            "employee_id" => $employee->id, "absence_date" => "2026-11-10",
        ]);

        $this->actingAs($cashier)->get("/justifications")->assertForbidden();
        $this->actingAs($cashier)->post("/justifications", [
            "employee_id" => $employee->id, "absence_date" => "2026-11-10", "reason" => "x",
        ])->assertForbidden();
        $this->actingAs($cashier)->patch("/justifications/{$justification->id}/review", [
            "decision" => "approved",
        ])->assertForbidden();
    }
}
