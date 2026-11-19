<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inside one store, staff below management change only the financial records
 * they registered; admin and owner manage all of them; cashiers stay out.
 */
class RecordOwnershipPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->accountant()->create();
        $this->colleague = User::factory()->accountant()->create();
    }

    private function expense(?User $by): Expense
    {
        $this->actingAs($by ?? $this->author);

        return Expense::create([
            "title" => "Detergente",
            "category" => "other",
            "amount" => 500,
            "expense_date" => now()->toDateString(),
            "user_id" => $by?->id,
        ]);
    }

    private function journalEntry(User $by): JournalEntry
    {
        return JournalEntry::create([
            "entry_number" => "LM-TEST-" . $by->id,
            "entry_date" => now()->toDateString(),
            "description" => "Acerto manual",
            "source" => "manual",
            "posted_by" => $by->id,
        ]);
    }

    private function salary(User $by): EmployeeSalary
    {
        $this->actingAs($by);

        return EmployeeSalary::factory()->create();
    }

    private function invoice(User $by): Invoice
    {
        $customer = Customer::create(["name" => "Hotel Polana", "phone" => "+258840001111", "address" => "Maputo", "is_credit_customer" => true]);

        return Invoice::create([
            "customer_id" => $customer->id,
            "period_start" => now()->startOfMonth()->toDateString(),
            "period_end" => now()->endOfMonth()->toDateString(),
            "status" => "draft",
            "created_by" => $by->id,
        ]);
    }

    public function test_authors_are_recorded_automatically(): void
    {
        $this->assertSame($this->author->id, $this->salary($this->author)->created_by);

        $this->actingAs($this->author);
        $expense = Expense::create(["title" => "Luz", "category" => "other", "amount" => 100, "expense_date" => now()->toDateString()]);
        $this->assertSame($this->author->id, $expense->user_id);
    }

    public function test_accountant_changes_only_own_records(): void
    {
        $records = [
            "expense" => [$this->expense($this->author), ["update", "delete"]],
            "journal entry" => [$this->journalEntry($this->author), ["update", "delete"]],
            "payslip" => [$this->salary($this->author), ["update", "delete", "pay"]],
            "invoice" => [$this->invoice($this->author), ["update", "void"]],
        ];

        foreach ($records as $label => [$record, $abilities]) {
            foreach ($abilities as $ability) {
                $this->assertTrue($this->author->can($ability, $record), "author $ability $label");
                $this->assertFalse($this->colleague->can($ability, $record), "colleague $ability $label");
            }
        }
    }

    public function test_management_changes_every_record_of_the_store(): void
    {
        $expense = $this->expense($this->author);
        $entry = $this->journalEntry($this->author);
        $salary = $this->salary($this->author);
        $invoice = $this->invoice($this->author);

        foreach ([User::factory()->create(), User::factory()->owner()->create()] as $manager) {
            $this->assertTrue($manager->can("update", $expense), "$manager->role expense");
            $this->assertTrue($manager->can("delete", $entry), "$manager->role journal");
            $this->assertTrue($manager->can("pay", $salary), "$manager->role payslip");
            $this->assertTrue($manager->can("void", $invoice), "$manager->role invoice");
        }
    }

    public function test_records_without_author_are_left_to_management(): void
    {
        $this->actingAs(User::factory()->create());
        $legacy = Expense::create(["title" => "Antiga", "category" => "other", "amount" => 50, "expense_date" => now()->toDateString()]);
        $legacy->forceFill(["user_id" => null])->save();

        $this->assertFalse($this->author->can("update", $legacy));
        $this->assertTrue(User::factory()->owner()->create()->can("update", $legacy));
    }

    public function test_cashier_cannot_touch_financial_records(): void
    {
        $cashier = User::factory()->cashier()->create();
        $expense = $this->expense($this->author);
        $invoice = $this->invoice($this->author);

        $this->assertFalse($cashier->can("update", $expense));
        $this->assertFalse($cashier->can("view", $invoice));
        $this->assertFalse($cashier->can("pay", $invoice));
        $this->actingAs($cashier)->get("/expenses/{$expense->id}/edit")->assertForbidden();
    }

    public function test_colleague_is_refused_on_the_routes(): void
    {
        $expense = $this->expense($this->author);
        $entry = $this->journalEntry($this->author);
        $salary = $this->salary($this->author);
        $invoice = $this->invoice($this->author);

        $this->actingAs($this->colleague);
        $this->get("/expenses/{$expense->id}/edit")->assertForbidden();
        $this->delete("/expenses/{$expense->id}")->assertForbidden();
        $this->get("/accounting/journal-entries/{$entry->id}/edit")->assertForbidden();
        $this->delete("/accounting/journal-entries/{$entry->id}")->assertForbidden();
        $this->get("/salaries/{$salary->id}/edit")->assertForbidden();
        $this->patch("/salaries/{$salary->id}/pay")->assertForbidden();
        $this->delete("/salaries/{$salary->id}")->assertForbidden();
        $this->post("/invoices/{$invoice->id}/issue")->assertForbidden();
        $this->post("/invoices/{$invoice->id}/void")->assertForbidden();

        $this->assertModelExists($expense);
        $this->assertModelExists($entry);
        $this->assertFalse($salary->fresh()->isPaid());
        $this->assertSame("draft", $invoice->fresh()->status);

        // Consultation stays open to the colleague.
        $this->get("/expenses")->assertOk()->assertSee(__("app.record_of_another_user"), false);
        $this->get("/invoices/{$invoice->id}")->assertOk();
    }

    public function test_author_keeps_their_journal_entry_after_a_manager_edits_it(): void
    {
        $entry = $this->journalEntry($this->author);

        $this->actingAs(User::factory()->owner()->create())->put("/accounting/journal-entries/{$entry->id}", [
            "entry_date" => now()->toDateString(),
            "description" => "Acerto revisto",
            "pairs" => [],
        ]);

        $this->assertSame($this->author->id, $entry->fresh()->posted_by);
    }

    public function test_author_can_still_work_their_own_records(): void
    {
        $expense = $this->expense($this->author);
        $salary = $this->salary($this->author);

        $this->actingAs($this->author);
        $this->get("/expenses/{$expense->id}/edit")->assertOk();
        $this->patch("/salaries/{$salary->id}/pay")->assertRedirect();
        $this->assertTrue($salary->fresh()->isPaid());
        $this->delete("/expenses/{$expense->id}")->assertRedirect(route("expenses.index"));
        $this->assertModelMissing($expense);
    }
}
