<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CustomerStatement;
use App\Services\Invoicing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessCustomerTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "0");
    }

    private function company(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            "name" => "Hotel Polana " . ++$this->seq,
            "type" => "business",
            "legal_name" => "Polana Hotelaria, SA",
            "nuit" => str_pad((string) (400000000 + $this->seq), 9, "0", STR_PAD_LEFT),
            "phone" => "+25884100" . str_pad((string) $this->seq, 3, "0", STR_PAD_LEFT),
            "is_credit_customer" => true,
            "credit_limit" => 0,
            "credit_terms_days" => 30,
            "billing_cycle" => "monthly",
        ], $overrides));
    }

    private function completedCreditOrder(Customer $customer, float $total, string $completedAt, array $overrides = []): Order
    {
        return Order::create(array_merge([
            "order_number" => "BIZ-" . str_pad((string) ++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "completed",
            "received_at" => $completedAt,
            "completed_at" => $completedAt,
            "total_price" => $total,
            "subtotal" => $total,
            "vat_rate" => 0,
            "vat_amount" => 0,
            "is_credit" => true,
            "due_date" => null,
        ], $overrides));
    }

    // ---- Phase 1: business customer identity ----

    public function test_business_customer_requires_legal_name_and_nuit(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/customers", [
            "name" => "Clinica Sorriso", "phone" => "+258841234567", "type" => "business", "nuit" => "12345",
        ])->assertSessionHasErrors(["legal_name", "nuit"]);

        $this->actingAs($admin)->post("/customers", [
            "name" => "Clinica Sorriso", "phone" => "+258841234567", "type" => "business",
            "legal_name" => "Clinica Sorriso, Lda", "nuit" => "401234567", "email" => "contas@sorriso.co.mz",
            "contact_person" => "Ana", "is_credit_customer" => 1, "billing_cycle" => "monthly", "discount_percent" => 5,
        ])->assertRedirect(route("customers.index"));

        $customer = Customer::firstWhere("nuit", "401234567");
        $this->assertTrue($customer->isBusiness());
        $this->assertTrue($customer->billsMonthly());
        $this->assertSame("Clinica Sorriso, Lda", $customer->fiscalName());
        $this->assertSame(5.0, (float) $customer->discount_percent);
    }

    public function test_individual_customer_drops_company_fields_and_cashier_cannot_grant_discount(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->post("/customers", [
            "name" => "Joao", "phone" => "+258849999999", "type" => "individual",
            "legal_name" => "Should be dropped", "nuit" => "409999999", "discount_percent" => 50,
        ])->assertRedirect();

        $customer = Customer::firstWhere("phone", "+258849999999");
        $this->assertNull($customer->legal_name);
        $this->assertNull($customer->nuit);
        $this->assertSame(0.0, (float) $customer->discount_percent);
    }

    // ---- Phase 4: negotiated prices ----

    public function test_order_uses_negotiated_price_then_discount_then_catalog(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["discount_percent" => 10, "billing_cycle" => "per_order"]);
        $negotiated = Service::create(["name" => "Lençóis", "type" => "by_weight", "price" => 1000, "estimated_days" => 1]);
        $discounted = Service::create(["name" => "Toalhas", "type" => "by_piece", "price" => 200, "estimated_days" => 1]);
        $package = Package::create(["name" => "Pacote 8kg", "weight_kg" => 8, "price" => 5000, "is_active" => true]);

        $this->actingAs($admin)->post(route("customers.prices.store", $customer), ["item" => "s" . $negotiated->id, "price" => 700])
            ->assertRedirect(route("customers.show", $customer));

        $this->actingAs($admin)->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->toDateString(),
            "purchase_order_ref" => "REQ-778",
            "items" => [
                ["service_id" => $negotiated->id, "weight" => 10],
                ["service_id" => $discounted->id, "amount" => 5],
                ["package_id" => $package->id, "amount" => 1],
            ],
        ])->assertRedirect();

        $order = Order::with("items")->latest("id")->first();
        $this->assertSame("REQ-778", $order->purchase_order_ref);
        $this->assertSame(700.0, (float) $order->items[0]->unit_price);   // negotiated
        $this->assertSame(180.0, (float) $order->items[1]->unit_price);   // 200 − 10%
        $this->assertSame(4500.0, (float) $order->items[2]->unit_price);  // 5000 − 10%
        $this->assertSame(7000 + 900 + 4500.0, (float) $order->total_price);
    }

    public function test_order_form_receives_pricing_rules_and_prices_can_be_removed(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company();
        $service = Service::create(["name" => "Fardas", "type" => "by_piece", "price" => 300, "estimated_days" => 1]);
        $price = CustomerPrice::create(["customer_id" => $customer->id, "service_id" => $service->id, "price" => 250]);

        $this->actingAs($admin)->get("/orders/create")->assertOk()->assertSee('"' . $service->id . '":250', false);
        $this->actingAs($admin)->get(route("customers.show", $customer))->assertOk()->assertSee(__("app.negotiated_prices"));

        $this->actingAs($admin)->delete(route("customers.prices.destroy", [$customer, $price]))->assertRedirect();
        $this->assertModelMissing($price);
    }

    // ---- Phase 2: consolidated invoices ----

    public function test_monthly_customer_orders_wait_for_the_invoice_due_date(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company();
        $service = Service::create(["name" => "Lavagem", "type" => "by_weight", "price" => 100, "estimated_days" => 1]);

        $this->actingAs($admin)->post("/orders", [
            "customer_id" => $customer->id, "received_at" => now()->toDateString(), "is_credit" => 1,
            "items" => [["service_id" => $service->id, "weight" => 5]],
        ])->assertRedirect();

        $order = Order::latest("id")->first();
        $this->assertTrue($order->is_credit);
        $this->assertNull($order->due_date);
    }

    public function test_draft_issue_numbering_and_due_dates(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["credit_terms_days" => 15]);
        $a = $this->completedCreditOrder($customer, 3000, "2026-10-05");
        $b = $this->completedCreditOrder($customer, 2000, "2026-10-20");
        $later = $this->completedCreditOrder($customer, 999, "2026-11-02");

        $this->actingAs($admin)->get(route("invoices.create", ["customer_id" => $customer->id, "period_start" => "2026-10-01", "period_end" => "2026-10-31"]))
            ->assertOk()->assertSee($a->order_number)->assertSee($b->order_number)->assertDontSee($later->order_number);

        $this->actingAs($admin)->post(route("invoices.store"), [
            "customer_id" => $customer->id, "period_start" => "2026-10-01", "period_end" => "2026-10-31",
            "orders" => [$a->id, $b->id],
        ])->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertTrue($invoice->isDraft());
        $this->assertNull($invoice->number);
        $this->assertSame(5000.0, (float) $invoice->total);

        $this->actingAs($admin)->get(route("invoices.print", $invoice))->assertNotFound();
        $this->actingAs($admin)->get(route("invoices.print", [$invoice, "preview" => 1]))->assertOk();

        $this->actingAs($admin)->post(route("invoices.issue", $invoice))->assertRedirect(route("invoices.show", $invoice));
        $invoice->refresh();

        $this->assertSame("FT-" . today()->year . "/0001", $invoice->number);
        $this->assertSame(today()->addDays(15)->toDateString(), $invoice->due_date->toDateString());
        $this->assertSame($invoice->due_date->toDateString(), $a->fresh()->due_date->toDateString());
        $this->assertSame($invoice->due_date->toDateString(), $b->fresh()->due_date->toDateString());
        $this->assertNull($later->fresh()->invoice_id);

        // An invoiced order cannot be deleted, and it cannot be billed twice.
        $this->actingAs($admin)->delete(route("orders.destroy", $a))->assertRedirect(route("orders.show", $a));
        $this->assertNotSoftDeleted($a);

        $second = $this->completedCreditOrder($customer, 100, "2026-10-30");
        $this->actingAs($admin)->post(route("invoices.store"), [
            "customer_id" => $customer->id, "period_start" => "2026-10-01", "period_end" => "2026-10-31", "orders" => [$a->id, $second->id],
        ]);
        $draft = Invoice::latest("id")->first();
        $this->assertSame([$second->id], $draft->orders()->pluck("id")->all());

        $this->actingAs($admin)->post(route("invoices.issue", $draft));
        $this->assertSame("FT-" . today()->year . "/0002", $draft->fresh()->number);

        $this->actingAs($admin)->get(route("invoices.show", $invoice))->assertOk()->assertSee("FT-");
        $this->actingAs($admin)->get(route("invoices.print", $invoice))->assertOk()->assertSee("Polana Hotelaria, SA")->assertSee($customer->nuit);
        $this->actingAs($admin)->get(route("invoices.index"))->assertOk()->assertSee($invoice->number);
    }

    public function test_voiding_releases_orders_and_is_blocked_after_payment(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["billing_cycle" => "per_order", "credit_terms_days" => 10]);
        $order = $this->completedCreditOrder($customer, 1000, "2026-10-05");
        $invoice = app(Invoicing::class)->createDraft($customer, now()->parse("2026-10-01"), now()->parse("2026-10-31"), null, $admin);
        app(Invoicing::class)->issue($invoice);

        $this->actingAs($admin)->post(route("invoices.void", $invoice))->assertSessionHasErrors("void_reason");
        $this->actingAs($admin)->post(route("invoices.void", $invoice), ["void_reason" => "Wrong customer"])->assertRedirect();

        $this->assertTrue($invoice->fresh()->isVoid());
        $order->refresh();
        $this->assertNull($order->invoice_id);
        $this->assertSame("2026-10-15", $order->due_date->toDateString()); // completion + own terms

        $again = app(Invoicing::class)->createDraft($customer, now()->parse("2026-10-01"), now()->parse("2026-10-31"), null, $admin);
        app(Invoicing::class)->issue($again);
        $this->actingAs($admin)->post(route("invoices.pay", $again), ["paid_at" => today()->toDateString(), "amount" => 400, "method" => "transfer", "transaction_ref" => "TRF400"]);

        $this->actingAs($admin)->post(route("invoices.void", $again), ["void_reason" => "x"])
            ->assertSessionHas("error", __("app.invoice_void_has_payments"));
        $this->assertTrue($again->fresh()->isIssued());
    }

    public function test_leaving_monthly_billing_gives_unbilled_orders_a_due_date(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["credit_terms_days" => 20]);
        $order = $this->completedCreditOrder($customer, 1000, "2026-10-01");

        $this->actingAs($admin)->put(route("customers.update", $customer), [
            "name" => $customer->name, "phone" => $customer->phone, "type" => "business",
            "legal_name" => $customer->legal_name, "nuit" => $customer->nuit,
            "is_credit_customer" => 1, "credit_terms_days" => 20, "billing_cycle" => "per_order",
        ])->assertRedirect();

        $this->assertSame("2026-10-21", $order->fresh()->due_date->toDateString());
    }

    public function test_monthly_command_creates_drafts_only_for_monthly_customers(): void
    {
        $monthly = $this->company();
        $perOrder = $this->company(["billing_cycle" => "per_order"]);
        $this->completedCreditOrder($monthly, 1500, "2026-10-10");
        $this->completedCreditOrder($perOrder, 1500, "2026-10-10");

        $this->artisan("invoices:generate-monthly", ["month" => "2026-10"])->assertSuccessful();

        $this->assertSame(1, Invoice::count());
        $invoice = Invoice::first();
        $this->assertSame($monthly->id, $invoice->customer_id);
        $this->assertTrue($invoice->isDraft());
        $this->assertSame("2026-10-31", $invoice->period_end->toDateString());

        // Running it again finds nothing left to bill.
        $this->artisan("invoices:generate-monthly", ["month" => "2026-10"])->assertSuccessful();
        $this->assertSame(1, Invoice::count());
    }

    // ---- Phase 3: grouped payments & statement ----

    public function test_grouped_payment_settles_oldest_debt_first_and_posts_each_order(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["billing_cycle" => "per_order"]);
        $old = $this->completedCreditOrder($customer, 3000, "2026-09-01", ["due_date" => "2026-10-01"]);
        $new = $this->completedCreditOrder($customer, 2000, "2026-10-01", ["due_date" => "2026-10-31"]);

        $this->actingAs($admin)->post(route("customers.payments.store", $customer), [
            "paid_at" => today()->toDateString(), "amount" => 9999, "method" => "transfer", "transaction_ref" => "TRF9999",
        ])->assertSessionHasErrors("amount");

        $this->actingAs($admin)->post(route("customers.payments.store", $customer), [
            "paid_at" => today()->toDateString(), "amount" => 4000, "method" => "transfer", "transaction_ref" => "TRF 5512",
        ])->assertRedirect(route("receivables.customer", $customer));

        $this->assertSame(0.0, $old->fresh()->remainingBalance());
        $this->assertSame(1000.0, $new->fresh()->remainingBalance());

        $payments = Payment::orderBy("id")->get();
        $this->assertCount(2, $payments);
        $this->assertSame($payments[0]->group_ref, $payments[1]->group_ref);
        $this->assertSame([3000.0, 1000.0], $payments->map(fn ($p) => (float) $p->amount)->all());

        // Each share settles the receivable: D 1.2.1 Depósitos à ordem / C 4.1.1 Clientes.
        $receivable = Account::findByCode("4.1.1");
        foreach ($payments as $payment) {
            $entry = JournalEntry::where("source", "payment")->where("source_id", $payment->id)->firstOrFail();
            $this->assertSame((float) $payment->amount, (float) $entry->lines()->where("account_id", $receivable->id)->sum("credit"));
        }

        $this->actingAs($admin)->get(route("payments.group-receipt", $payments[0]->group_ref))
            ->assertOk()->assertSee("4,000")->assertSee($old->order_number)->assertSee($new->order_number);
    }

    public function test_invoice_list_shows_unpaid_order_and_consolidated_invoices(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["billing_cycle" => "per_order"]);
        $walkIn = Customer::create(["name" => "Cliente Balcão", "phone" => "+258840009999"]);

        // An ordinary order still owing, one fully paid, and two credit orders billed on an FT.
        $owing = $this->completedCreditOrder($walkIn, 800, today()->toDateString(), ["is_credit" => false, "status" => "received", "completed_at" => null]);
        $paid = $this->completedCreditOrder($walkIn, 500, today()->toDateString(), ["is_credit" => false, "status" => "received", "completed_at" => null]);
        Payment::create(["order_id" => $paid->id, "paid_at" => today()->toDateString(), "amount" => 500, "method" => "cash"]);
        $billed = $this->completedCreditOrder($customer, 3000, "2026-10-05");
        $invoice = app(Invoicing::class)->createDraft($customer, \Carbon\Carbon::parse("2026-10-01"), \Carbon\Carbon::parse("2026-10-31"), null, $admin);

        // A draft FT is not an invoice yet: its order is listed with its own invoice.
        $this->actingAs($admin)->get(route("invoices.index"))->assertOk()
            ->assertSee($owing->order_number)->assertSee($billed->order_number)->assertDontSee($paid->order_number)
            ->assertDontSee(route("invoices.create"));

        app(Invoicing::class)->issue($invoice);

        // Once issued, the order is billed on the FT and only the FT is listed for it.
        $this->actingAs($admin)->get(route("invoices.index"))->assertOk()
            ->assertSee($invoice->fresh()->number)->assertDontSee($billed->order_number)->assertSee($owing->order_number)
            ->assertSee("3,800");

        $this->actingAs($admin)->get(route("invoices.index", ["kind" => "order"]))->assertOk()->assertDontSee($invoice->fresh()->number);
        $this->actingAs($admin)->get(route("invoices.index", ["status" => "draft"]))->assertOk()->assertDontSee($owing->order_number);
    }

    public function test_grouped_cash_payment_records_the_change_once(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company(["billing_cycle" => "per_order"]);
        $this->completedCreditOrder($customer, 3000, "2026-09-01", ["due_date" => "2026-10-01"]);
        $this->completedCreditOrder($customer, 2000, "2026-10-01", ["due_date" => "2026-10-31"]);

        $this->actingAs($admin)->post(route("customers.payments.store", $customer), [
            "paid_at" => today()->toDateString(), "amount" => 4000, "method" => "cash", "tendered" => 3000,
        ])->assertSessionHasErrors("tendered");

        $this->actingAs($admin)->post(route("customers.payments.store", $customer), [
            "paid_at" => today()->toDateString(), "amount" => 4000, "method" => "cash", "tendered" => 5000,
        ])->assertSessionHas("success", fn ($msg) => str_contains($msg, "1,000"));

        $payments = Payment::orderBy("id")->get();
        $this->assertNull($payments[0]->change());
        $this->assertSame(1000.0, $payments[1]->change());

        $this->actingAs($admin)->get(route("payments.group-receipt", $payments[0]->group_ref))
            ->assertOk()->assertSee(__("app.tendered"))->assertSee("5,000")->assertSee(__("app.change_due"));
    }

    public function test_invoice_payment_marks_it_paid_and_statement_balances(): void
    {
        $admin = User::factory()->create();
        $customer = $this->company();
        $a = $this->completedCreditOrder($customer, 3000, "2026-10-05");
        $b = $this->completedCreditOrder($customer, 2000, "2026-10-20");
        $outside = $this->completedCreditOrder($customer, 700, "2026-11-03");
        $invoicing = app(Invoicing::class);
        $invoice = $invoicing->issue($invoicing->createDraft($customer, now()->parse("2026-10-01"), now()->parse("2026-10-31"), null, $admin));

        // The invoice payment only touches the invoice's orders.
        $this->actingAs($admin)->post(route("invoices.pay", $invoice), [
            "paid_at" => today()->toDateString(), "amount" => 5000, "method" => "transfer", "transaction_ref" => "TRF5000",
        ])->assertRedirect(route("invoices.show", $invoice));

        $invoice->refresh()->load("orders");
        $this->assertTrue($invoice->isPaid());
        $this->assertSame("paid", $invoice->displayStatus());
        $this->assertSame(700.0, $outside->fresh()->remainingBalance());
        $this->assertSame(2, Payment::where("invoice_id", $invoice->id)->count());

        $data = app(CustomerStatement::class)->build($customer, now()->parse("2026-01-01"), today()->endOfDay());
        $this->assertSame(0.0, $data["opening"]);
        $this->assertSame(700.0, $data["closing"]);
        $this->assertSame($customer->creditOutstandingBalance(), $data["closing"]);

        $this->actingAs($admin)->get(route("customers.statement", ["customer" => $customer, "from" => "2026-01-01"]))
            ->assertOk()->assertSee(__("app.statement_of_account"))->assertSee($invoice->number);
        $this->actingAs($admin)->get(route("receivables.customer", $customer))->assertOk()->assertSee($invoice->number);
    }

    public function test_business_pages_are_management_only(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = $this->company();

        $this->actingAs($cashier)->get(route("invoices.index"))->assertForbidden();
        $this->actingAs($cashier)->get(route("customers.statement", $customer))->assertForbidden();
        $this->actingAs($cashier)->post(route("customers.payments.store", $customer), ["paid_at" => today()->toDateString(), "amount" => 1, "method" => "cash"])->assertForbidden();
        $this->actingAs($cashier)->post(route("customers.prices.store", $customer), ["item" => "s1", "price" => 1])->assertForbidden();
        $this->actingAs($cashier)->get(route("customers.show", $customer))->assertOk()->assertDontSee(__("app.negotiated_prices"));
    }

    public function test_company_identity_is_saved_and_printed(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post(route("admin.system-settings.general.update"), [
            "company_name" => "WS Clean & Laundry", "company_legal_name" => "WS Clean & Laundry Serviços, Lda", "company_nuit" => "400111222",
            "invoice_bank_details" => "BCI · NIB 0008 0000 1234",
        ])->assertRedirect(route("admin.system-settings.general"));

        $this->assertSame("400111222", SiteSetting::get("company_nuit"));

        $customer = $this->company();
        $this->completedCreditOrder($customer, 100, "2026-10-01");
        $this->actingAs($admin)->get(route("customers.statement", $customer))->assertOk()
            ->assertSee("400111222")->assertSee("NIB 0008 0000 1234");
    }
}
