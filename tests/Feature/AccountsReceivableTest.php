<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsReceivableTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function creditOrder(Customer $customer, float $total, ?string $due, float $paid = 0, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            "order_number" => "RCV-" . str_pad(++$this->seq, 4, "0", STR_PAD_LEFT),
            "customer_id" => $customer->id,
            "status" => "completed",
            "received_at" => now()->toDateString(),
            "completed_at" => now()->toDateString(),
            "total_price" => $total,
            "vat_rate" => 0,
            "is_credit" => true,
            "due_date" => $due,
        ], $overrides));

        if ($paid > 0) {
            Payment::create([
                "order_id" => $order->id,
                "user_id" => $this->admin()->id,
                "paid_at" => now()->toDateString(),
                "amount" => $paid,
                "method" => "cash",
            ]);
        }

        return $order;
    }

    private function hotel(): Customer
    {
        return Customer::create([
            "name" => "Hotel Costa do Sol",
            "phone" => "+258840070" . str_pad(++$this->seq, 2, "0", STR_PAD_LEFT),
            "is_credit_customer" => true,
            "credit_limit" => 200000,
            "credit_terms_days" => 30,
        ]);
    }

    public function test_cashier_cannot_access_receivables(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/receivables")->assertForbidden();
        $this->actingAs($cashier)->get("/receivables/export")->assertForbidden();
    }

    public function test_receivables_index_lists_open_credit_orders_with_aging(): void
    {
        $hotel = $this->hotel();
        $open = $this->creditOrder($hotel, 50000, now()->addDays(10)->toDateString(), 10000);
        $overdue = $this->creditOrder($hotel, 30000, now()->subDays(45)->toDateString());
        $settled = $this->creditOrder($hotel, 20000, now()->addDays(5)->toDateString(), 20000);
        $inProgress = $this->creditOrder($hotel, 90000, null, 0, ["status" => "washing"]);

        $this->actingAs($this->admin())->get("/receivables")
            ->assertOk()
            ->assertSee($open->order_number)
            ->assertSee($overdue->order_number)
            ->assertDontSee($settled->order_number)
            ->assertDontSee($inProgress->order_number)
            ->assertSee("Hotel Costa do Sol");

        // 40 000 open on first order + 30 000 overdue => 70 000 total outstanding
        $this->actingAs($this->admin())->get("/receivables")->assertSee("70,000");
    }

    public function test_overdue_filter_shows_only_overdue_orders(): void
    {
        $hotel = $this->hotel();
        $open = $this->creditOrder($hotel, 50000, now()->addDays(10)->toDateString());
        $overdue = $this->creditOrder($hotel, 30000, now()->subDays(5)->toDateString());

        $this->actingAs($this->admin())->get("/receivables?status=overdue")
            ->assertSee($overdue->order_number)
            ->assertDontSee($open->order_number);
    }

    public function test_search_filters_by_customer_or_order_number(): void
    {
        $hotel = $this->hotel();
        $other = $this->creditOrder($hotel, 15000, now()->addDays(20)->toDateString());
        $target = $this->creditOrder($hotel, 25000, now()->addDays(20)->toDateString());

        $this->actingAs($this->admin())->get("/receivables?search=" . $target->order_number)
            ->assertSee($target->order_number)
            ->assertDontSee($other->order_number);
    }

    public function test_customer_statement_shows_credit_order_history(): void
    {
        $hotel = $this->hotel();
        $first = $this->creditOrder($hotel, 40000, now()->addDays(10)->toDateString());
        $second = $this->creditOrder($hotel, 60000, now()->addDays(10)->toDateString());

        $this->actingAs($this->admin())->get("/receivables/customer/{$hotel->id}")
            ->assertOk()
            ->assertSee($first->order_number)
            ->assertSee($second->order_number)
            ->assertSee("Hotel Costa do Sol");
    }

    public function test_statement_rejects_non_credit_customer(): void
    {
        $plain = Customer::create(["name" => "Avulso", "phone" => "+258840099988"]);

        $this->actingAs($this->admin())->get("/receivables/customer/{$plain->id}")->assertNotFound();
    }

    public function test_receivables_exports_xlsx(): void
    {
        $hotel = $this->hotel();
        $this->creditOrder($hotel, 30000, now()->subDays(45)->toDateString());

        $response = $this->actingAs($this->admin())->get("/receivables/export");
        $response->assertOk();
        $this->assertStringContainsString("spreadsheet", $response->headers->get("Content-Type"));
    }

    public function test_dashboard_shows_outstanding_receivables_to_owner(): void
    {
        $hotel = $this->hotel();
        $this->creditOrder($hotel, 50000, now()->addDays(10)->toDateString(), 20000);

        $this->actingAs(User::factory()->owner()->create())->get("/dashboard")
            ->assertOk()
            ->assertSee("30,000");
    }

    public function test_journal_balances_after_credit_sale_settlement(): void
    {
        $hotel = $this->hotel();
        $order = $this->creditOrder($hotel, 50000, now()->addDays(10)->toDateString());
        $order->postCreditSaleToJournal();

        $payment = Payment::create([
            "order_id" => $order->id,
            "user_id" => $this->admin()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 50000,
            "method" => "transfer",
        ]);
        $payment->postToJournal();

        // 4.1.1 nets to zero after full settlement; revenue booked once on 7.2
        $clientes = Account::findByCode("4.1.1");
        $this->assertEqualsWithDelta(0.0, (float) $clientes->lines()->sum("debit") - (float) $clientes->lines()->sum("credit"), 0.01);

        $servicos = Account::findByCode("7.2");
        $this->assertSame(50000.0, (float) $servicos->lines()->sum("credit"));
    }
}
