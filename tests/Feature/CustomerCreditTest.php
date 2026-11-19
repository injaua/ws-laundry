<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CustomerCreditTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "0");
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function creditCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            "name" => "Hotel Matola",
            "phone" => "+25884000" . str_pad(++$this->seq, 3, "0", STR_PAD_LEFT),
            "address" => "Matola",
            "is_credit_customer" => true,
            "credit_limit" => 50000,
            "credit_terms_days" => 30,
        ], $overrides));
    }

    private function service(float $price = 1000): Service
    {
        return Service::create(["name" => "Lavanda " . ++$this->seq, "type" => "by_weight", "price" => $price, "estimated_days" => 2]);
    }

    private function creditOrder(Customer $customer, float $total = 10000, array $overrides = []): Order
    {
        return Order::create(array_merge([
            "order_number" => "CRD-" . str_pad(++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "completed",
            "received_at" => now()->toDateString(),
            "delivery_date" => now()->toDateString(),
            "completed_at" => now()->toDateString(),
            "total_price" => $total,
            "vat_rate" => 0,
            "is_credit" => true,
            "due_date" => now()->addDays(30)->toDateString(),
        ], $overrides));
    }

    private function postOrder(User $user, Customer $customer, Service $service, array $extra = []): TestResponse
    {
        return $this->actingAs($user)->post("/orders", array_merge([
            "customer_id" => $customer->id,
            "received_at" => now()->toDateString(),
            "items" => [["service_id" => $service->id, "weight" => 10]],
        ], $extra));
    }

    public function test_customer_credit_settings_are_saved(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post("/customers", [
            "name" => "Uniformes SARL",
            "phone" => "+258841112223",
            "address" => "Maputo",
            "is_credit_customer" => "1",
            "credit_limit" => 120000,
            "credit_terms_days" => 45,
        ])->assertRedirect();

        $customer = Customer::firstWhere("phone", "+258841112223");
        $this->assertTrue($customer->is_credit_customer);
        $this->assertSame(120000.0, (float) $customer->credit_limit);
        $this->assertSame(45, (int) $customer->credit_terms_days);
    }

    public function test_credit_order_gets_due_date_from_terms(): void
    {
        $customer = $this->creditCustomer(["credit_terms_days" => 30]);

        $this->postOrder($this->admin(), $customer, $this->service(), ["is_credit" => "1"])
            ->assertSessionHasNoErrors();

        $order = $customer->orders()->first();
        $this->assertTrue($order->is_credit);
        // delivery auto-fills to entry + 2 lead days, due = delivery + 30
        $this->assertSame(
            now()->addDays(32)->toDateString(),
            $order->due_date->toDateString()
        );
        $this->assertSame(10000.0, (float) $order->total_price);
    }

    public function test_non_credit_customer_cannot_be_billed_on_credit(): void
    {
        $plain = Customer::create(["name" => "Avulso", "phone" => "+258840000555"]);

        $this->postOrder($this->admin(), $plain, $this->service(), ["is_credit" => "1"])
            ->assertSessionHasErrors("is_credit");

        $this->assertSame(0, $plain->orders()->count());
    }

    public function test_credit_limit_blocks_until_outstanding_plus_order_fit_the_limit(): void
    {
        $customer = $this->creditCustomer(["credit_limit" => 50000]);
        $service = $this->service();

        // 45 000 already outstanding; new 10 000 order => 55 000 exposure > 50 000
        $this->creditOrder($customer, 45000);

        $this->postOrder($this->admin(), $customer, $service, ["is_credit" => "1"])
            ->assertSessionHasErrors("credit_override_note");

        // With an explicit override justification the admin/owner can proceed
        $this->postOrder($this->admin(), $customer, $service, [
            "is_credit" => "1",
            "credit_override_note" => "Gerente autorizou",
        ])->assertSessionHasNoErrors();

        $order = $customer->orders()->latest("id")->first();
        $this->assertSame("Gerente autorizou", $order->credit_override_note);
    }

    public function test_unlimited_credit_customer_is_only_blocked_by_overdue(): void
    {
        $customer = $this->creditCustomer(["credit_limit" => 0]);
        $this->creditOrder($customer, 500000, ["due_date" => now()->subDays(10)->toDateString()]);

        $this->postOrder($this->admin(), $customer, $this->service(), ["is_credit" => "1"])
            ->assertSessionHasErrors("credit_override_note");
    }

    public function test_overdue_customer_is_blocked_even_within_limit(): void
    {
        $customer = $this->creditCustomer(["credit_limit" => 100000]);
        $this->creditOrder($customer, 10000, ["due_date" => now()->subDay()->toDateString()]);

        $this->postOrder($this->admin(), $customer, $this->service(), ["is_credit" => "1"])
            ->assertSessionHasErrors("credit_override_note");
    }

    public function test_cashier_cannot_override_credit_rules(): void
    {
        $cashier = User::factory()->cashier()->create();
        $customer = $this->creditCustomer(["credit_limit" => 5000]);

        $this->postOrder($cashier, $customer, $this->service(), [
            "is_credit" => "1",
            "credit_override_note" => "tentativa",
        ])->assertSessionHasErrors("is_credit");

        $this->assertSame(0, $customer->orders()->count());
    }

    public function test_completed_credit_order_posts_receivable_entry(): void
    {
        SiteSetting::set("vat_rate", "16");
        $customer = $this->creditCustomer();
        $order = $this->creditOrder($customer, 11600, ["total_price" => 11600, "vat_rate" => 16, "status" => "delivering", "completed_at" => null]);

        $this->actingAs($this->admin())->put("/orders/{$order->id}", [
            "customer_id" => $customer->id,
            "status" => "completed",
            "received_at" => $order->received_at->toDateString(),
            "completed_at" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "credit_sale");
        $this->assertNotNull($entry);

        $lines = $entry->lines()->get()->keyBy("account_id");
        $this->assertSame(11600.0, (float) $lines[Account::findByCode("4.1.1")->id]->debit);
        $this->assertSame(10000.0, (float) $lines[Account::findByCode("7.2")->id]->credit);
        $this->assertSame(1600.0, (float) $lines[Account::findByCode("4.4.3.3")->id]->credit);
    }

    public function test_payment_on_credit_order_settles_receivable_without_new_revenue(): void
    {
        $customer = $this->creditCustomer();
        $order = $this->creditOrder($customer, 10000);
        $order->postCreditSaleToJournal();

        $payment = Payment::create([
            "order_id" => $order->id,
            "user_id" => $this->admin()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 4000,
            "method" => "cash",
        ]);
        $entry = $payment->postToJournal();

        $this->assertNotNull($entry);
        $lines = $entry->lines()->get()->keyBy("account_id");
        $this->assertSame(4000.0, (float) $lines[Account::findByCode("1.1")->id]->debit);
        $this->assertSame(4000.0, (float) $lines[Account::findByCode("4.1.1")->id]->credit);
        $this->assertArrayNotHasKey(Account::findByCode("7.2")->id, $lines);

        // 7.2 total stays at the revenue recognised on completion
        $services = Account::findByCode("7.2");
        $this->assertSame(10000.0, (float) $services->lines()->sum("credit"));
    }

    public function test_cashier_can_record_payment_on_completed_credit_order(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->openTill($cashier);
        $customer = $this->creditCustomer();
        $order = $this->creditOrder($customer, 10000);

        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 6000,
            "method" => "mpesa",
            "transaction_ref" => "MP6000CRD",
        ])->assertSessionHasNoErrors();

        $this->assertSame(6000.0, (float) $order->fresh()->payments()->sum("amount"));

        // and once fully paid no further payment is accepted
        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 5000,
            "method" => "cash",
        ])->assertSessionHasErrors("amount");
    }

    public function test_fully_paid_credit_order_is_no_longer_outstanding(): void
    {
        $customer = $this->creditCustomer();
        $order = $this->creditOrder($customer, 10000);

        Payment::create([
            "order_id" => $order->id,
            "user_id" => $this->admin()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 10000,
            "method" => "cash",
        ]);

        $this->assertSame(0.0, $customer->creditOutstandingBalance());
        $this->assertFalse($order->isOverdue());
    }

    public function test_overdue_detection_and_aging_bucket(): void
    {
        $customer = $this->creditCustomer();
        $order = $this->creditOrder($customer, 10000, ["due_date" => now()->subDays(45)->toDateString()]);

        $this->assertTrue($order->isOverdue());
        $this->assertSame(45, $order->daysOverdue());
        $this->assertSame(10000.0, $customer->creditOutstandingBalance());
        $this->assertTrue($customer->hasOverdueCredit());
    }

    public function test_cash_order_journal_is_unchanged(): void
    {
        $customer = $this->creditCustomer();
        $order = $this->creditOrder($customer, 10000, ["is_credit" => false, "due_date" => null]);

        $payment = Payment::create([
            "order_id" => $order->id,
            "user_id" => $this->admin()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 10000,
            "method" => "cash",
        ]);
        $entry = $payment->postToJournal();

        $lines = $entry->lines()->get()->keyBy("account_id");
        $this->assertSame(10000.0, (float) $lines[Account::findByCode("1.1")->id]->debit);
        $this->assertSame(10000.0, (float) $lines[Account::findByCode("7.2")->id]->credit);
    }
}
