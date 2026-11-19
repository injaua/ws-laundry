<?php

namespace Tests\Feature;

use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\SiteSetting;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Documents\DocumentResponse;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Main documents as A4 pages and PDFs (?format=pdf), accounting / payroll reports as
 * Excel or PDF (?format=xlsx|pdf), with the same permissions as the pages themselves.
 */
class DocumentExportTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "16");
    }

    // ---------- Documents ----------

    public function test_order_invoice_is_an_a4_page_with_a_pdf_download(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->order();

        $this->actingAs($cashier)->get("/orders/{$order->id}/document")
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee(__("app.download_pdf"))
            ->assertSee("format=pdf", false);

        $this->assertPdf($this->actingAs($cashier)->get("/orders/{$order->id}/document?format=pdf"), "fatura-" . strtolower($order->order_number) . ".pdf");
    }

    public function test_payment_receipt_pdf(): void
    {
        $admin = User::factory()->create();
        $order = $this->order();
        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 580, "method" => "cash",
        ])->assertSessionHasNoErrors();
        $payment = Payment::latest("id")->first();

        $this->actingAs($admin)->get("/payments/{$payment->id}/document")
            ->assertOk()->assertSee("REC-" . str_pad($payment->id, 6, "0", STR_PAD_LEFT))->assertSee(money(580));
        $this->assertPdf($this->actingAs($admin)->get("/payments/{$payment->id}/document?format=pdf"));
    }

    public function test_credit_note_pdf(): void
    {
        $admin = User::factory()->create();
        $order = $this->order();
        $note = CreditNote::create([
            "number" => "NC0001", "order_id" => $order->id, "customer_id" => $order->customer_id, "issued_at" => now(),
            "reason" => "damage", "description" => "Camisa danificada", "amount" => 116, "net_amount" => 100,
            "vat_amount" => 16, "vat_rate" => 16, "settlement" => "deduct", "user_id" => $admin->id,
        ]);

        $this->actingAs($admin)->get("/credit-notes/{$note->id}/document")->assertOk()->assertSee("NC0001")->assertSee("Camisa danificada");
        $this->assertPdf($this->actingAs($admin)->get("/credit-notes/{$note->id}/document?format=pdf"), "nota-de-credito-nc0001.pdf");
    }

    public function test_consolidated_invoice_pdf_and_draft_stays_hidden(): void
    {
        $accountant = User::factory()->accountant()->create();
        $customer = $this->businessCustomer();
        $invoice = Invoice::create([
            "number" => "FT-2026-0001", "customer_id" => $customer->id, "period_start" => now()->startOfMonth(), "period_end" => now(),
            "issue_date" => now(), "due_date" => now()->addDays(30), "subtotal" => 1000, "vat_amount" => 160, "total" => 1160, "status" => "issued",
        ]);

        $this->assertPdf($this->actingAs($accountant)->get("/invoices/{$invoice->id}/print?format=pdf"), "fatura-ft-2026-0001.pdf");

        $invoice->update(["status" => "draft", "number" => null]);
        $this->actingAs($accountant)->get("/invoices/{$invoice->id}/print?format=pdf")->assertNotFound();
    }

    public function test_customer_statement_as_pdf_and_excel(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = $this->businessCustomer();
        $this->order(["customer_id" => $customer->id, "is_credit" => true, "status" => "completed", "completed_at" => now()]);

        $this->actingAs($owner)->get("/customers/{$customer->id}/statement")->assertOk()->assertSee("format=xlsx", false);
        $this->assertPdf($this->actingAs($owner)->get("/customers/{$customer->id}/statement?format=pdf"));

        $sheet = $this->sheet($this->actingAs($owner)->get("/customers/{$customer->id}/statement?format=xlsx"));
        $this->assertSame(SystemSettings::legalName(), $sheet->getCell("A1")->getValue());
        $this->assertStringContainsString($customer->fiscalName(), (string) $sheet->getCell("A2")->getValue());
        $this->assertSame(__("app.opening_balance"), $sheet->getCell("A5")->getValue());
    }

    public function test_payslip_for_payroll_and_for_the_employee_only(): void
    {
        $accountant = User::factory()->accountant()->create();
        $cashier = User::factory()->cashier()->create();
        $mine = EmployeeSalary::factory()->create([
            "employee_id" => Employee::factory()->create(["name" => "Ana Macuácua", "user_id" => $cashier->id])->id,
            "base_salary" => 15000, "bonus" => 1000, "deduction" => 500, "net_salary" => 15500,
        ]);
        $other = EmployeeSalary::factory()->create(["net_salary" => 9000]);

        $this->actingAs($accountant)->get("/salaries/{$mine->id}/payslip")
            ->assertOk()->assertSee("Ana Macuácua")->assertSee(__("app.employee_signature"));
        $this->assertPdf($this->actingAs($accountant)->get("/salaries/{$mine->id}/payslip?format=pdf"));

        $this->assertPdf($this->actingAs($cashier)->get("/my-payslips/{$mine->id}?format=pdf"));
        $this->actingAs($cashier)->get("/my-payslips/{$other->id}")->assertNotFound();
        $this->actingAs($cashier)->get("/salaries/{$mine->id}/payslip")->assertForbidden();
    }

    public function test_purchase_order_pdf(): void
    {
        $owner = User::factory()->owner()->create();
        $supplier = Supplier::create(["name" => "Química Lda", "phone" => "867314599"]);
        $item = InventoryItem::create(["kind" => "product", "name" => "Detergente", "type" => "detergent", "unit" => "l"]);
        $purchase = PurchaseOrder::create(["po_number" => "OC-0001", "supplier_id" => $supplier->id, "user_id" => $owner->id, "order_date" => now(), "status" => "draft", "total" => 500]);
        $purchase->items()->create(["inventory_item_id" => $item->id, "quantity" => 5, "unit_cost" => 100, "subtotal" => 500, "received_qty" => 0]);

        $this->actingAs($owner)->get("/resources/purchases/{$purchase->id}/document")->assertOk()->assertSee("Química Lda")->assertSee("Detergente");
        $this->assertPdf($this->actingAs($owner)->get("/resources/purchases/{$purchase->id}/document?format=pdf"), "ordem-de-compra-oc-0001.pdf");
    }

    public function test_till_report_pdf_follows_the_till_policy(): void
    {
        $cashier = User::factory()->cashier()->create();
        $colleague = User::factory()->cashier()->create();
        $session = $this->openTill($cashier, 500);

        $this->actingAs($cashier)->get("/cash-sessions/{$session->id}/document")->assertOk()->assertSee(money(500));
        $this->assertPdf($this->actingAs($cashier)->get("/cash-sessions/{$session->id}/document?format=pdf"));
        $this->actingAs($colleague)->get("/cash-sessions/{$session->id}/document")->assertForbidden();
    }

    // ---------- Reports ----------

    public function test_trial_balance_excel_balances_and_pdf_downloads(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->paidOrder();

        $sheet = $this->sheet($this->actingAs($accountant)->get("/accounting/trial-balance?format=xlsx"));
        $this->assertSame(__("app.trial_balance"), $sheet->getCell("A2")->getValue());
        $total = $this->rowWith($sheet, __("app.total"));
        $this->assertGreaterThan(0, $sheet->getCell("C{$total}")->getValue());
        $this->assertEqualsWithDelta($sheet->getCell("C{$total}")->getValue(), $sheet->getCell("D{$total}")->getValue(), 0.001);
        $this->assertSame("#,##0.00", $sheet->getStyle("C{$total}")->getNumberFormat()->getFormatCode());

        $this->assertPdf($this->actingAs($accountant)->get("/accounting/trial-balance?format=pdf"));
    }

    public function test_balance_sheet_income_statement_and_vat_exports(): void
    {
        $owner = User::factory()->owner()->create();
        $this->paidOrder();

        $balance = $this->sheet($this->actingAs($owner)->get("/accounting/balance-sheet?format=xlsx"));
        $this->assertNotNull($this->rowWith($balance, __("app.total_liabilities_and_equity")));

        $income = $this->sheet($this->actingAs($owner)->get("/accounting/income-statement?format=xlsx"));
        $net = $this->rowWith($income, __("app.net_result"));
        $this->assertEqualsWithDelta(1000.0, $income->getCell("C{$net}")->getValue(), 0.001);

        $vat = $this->sheet($this->actingAs($owner)->get("/accounting/vat-settlement?format=xlsx"));
        $this->assertSame(__("app.output_vat_pending"), $vat->getCell("A5")->getValue());
        $this->assertEqualsWithDelta(160.0, $vat->getCell("B5")->getValue(), 0.001);

        foreach (["balance-sheet", "income-statement", "vat-settlement"] as $page) {
            $this->assertPdf($this->actingAs($owner)->get("/accounting/{$page}?format=pdf"));
        }
    }

    public function test_journal_export_keeps_the_page_filters(): void
    {
        $accountant = User::factory()->accountant()->create();
        $this->paidOrder();

        $all = $this->sheet($this->actingAs($accountant)->get("/accounting/journal-entries?format=xlsx"));
        $this->assertNotNull($this->rowWith($all, __("app.source_payment"), "D"));

        $none = $this->sheet($this->actingAs($accountant)->get("/accounting/journal-entries?format=xlsx&source=expense"));
        $this->assertNull($this->rowWith($none, __("app.source_payment"), "D"));

        $this->assertPdf($this->actingAs($accountant)->get("/accounting/journal-entries?format=pdf"));
    }

    public function test_salary_summary_excel(): void
    {
        $owner = User::factory()->owner()->create();
        EmployeeSalary::factory()->create(["period_month" => 3, "period_year" => 2026, "base_salary" => 10000, "net_salary" => 10000, "status" => "paid", "paid_at" => now()]);
        EmployeeSalary::factory()->create(["period_month" => 3, "period_year" => 2026, "base_salary" => 8000, "net_salary" => 8000]);

        $sheet = $this->sheet($this->actingAs($owner)->get("/salaries/summary?month=3&year=2026&format=xlsx"));
        $total = $this->rowWith($sheet, __("app.month_total"));
        $this->assertEqualsWithDelta(18000.0, $sheet->getCell("F{$total}")->getValue(), 0.001);
        $this->assertEqualsWithDelta(10000.0, $sheet->getCell("B5")->getValue(), 0.001); // total paid
    }

    public function test_exports_respect_page_permissions_and_ignore_unknown_formats(): void
    {
        $cashier = User::factory()->cashier()->create();
        $accountant = User::factory()->accountant()->create();

        $this->actingAs($cashier)->get("/accounting/trial-balance?format=xlsx")->assertForbidden();
        $this->actingAs($cashier)->get("/salaries/summary?format=pdf")->assertForbidden();

        $this->actingAs($accountant)->get("/accounting/trial-balance?format=csv")
            ->assertOk()->assertHeader("content-type", "text/html; charset=utf-8");
    }

    public function test_download_names_are_safe(): void
    {
        $this->assertSame("fatura-ft-2026-0001.pdf", DocumentResponse::filename("Fatura FT-2026/0001", "pdf"));
        $this->assertSame("documento.xlsx", DocumentResponse::filename("///", "xlsx"));
    }

    // ---------- Helpers ----------

    private function assertPdf(TestResponse $response, ?string $filename = null): void
    {
        $response->assertOk()->assertHeader("content-type", "application/pdf");
        $this->assertStringStartsWith("%PDF-", $response->getContent());
        if ($filename) {
            $this->assertStringContainsString("filename={$filename}", (string) $response->headers->get("content-disposition"));
        }
    }

    private function sheet(TestResponse $response): Worksheet
    {
        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $this->assertStringContainsString(".xlsx", (string) $response->headers->get("content-disposition"));

        return IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
    }

    /** First row whose cell in $column (or column B) reads $text. */
    private function rowWith(Worksheet $sheet, string $text, string $column = "B"): ?int
    {
        for ($r = 1; $r <= $sheet->getHighestRow(); $r++) {
            if ($sheet->getCell("{$column}{$r}")->getValue() === $text || $sheet->getCell("A{$r}")->getValue() === $text) {
                return $r;
            }
        }

        return null;
    }

    private function order(array $overrides = []): Order
    {
        $customerId = $overrides["customer_id"] ?? Customer::create([
            "name" => "Cliente " . ++$this->seq, "phone" => "8400" . str_pad((string) $this->seq, 5, "0", STR_PAD_LEFT), "address" => "Maputo",
        ])->id;

        return Order::create(array_merge([
            "order_number" => "DOC-" . str_pad((string) ++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customerId,
            "status" => "ready",
            "received_at" => now()->toDateString(),
            "delivery_date" => now()->toDateString(),
            "total_price" => 1160,
            "subtotal" => 1000,
            "vat_rate" => 16,
            "vat_amount" => 160,
        ], $overrides));
    }

    /** An order paid in cash: posts 1.1 against 7.2 + 4.4.3.3 in the journal. */
    private function paidOrder(): Order
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create())->post("/payments", [
            "order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 1160, "method" => "cash",
        ])->assertSessionHasNoErrors();

        return $order;
    }

    private function businessCustomer(): Customer
    {
        return Customer::create([
            "name" => "Hotel " . ++$this->seq, "type" => "business", "legal_name" => "Hotelaria " . $this->seq . ", SA",
            "nuit" => (string) (400000000 + $this->seq), "phone" => "+25884200" . str_pad((string) $this->seq, 3, "0", STR_PAD_LEFT),
            "is_credit_customer" => true, "credit_limit" => 0, "credit_terms_days" => 30,
        ]);
    }
}
