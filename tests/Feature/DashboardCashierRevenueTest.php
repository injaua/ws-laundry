<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCashierRevenueTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function makeOrder(): Order
    {
        $customer = Customer::create(["name" => "Teste", "phone" => "+2588400000" . (++$this->seq) . "0"]);

        return Order::create([
            "order_number" => "ORD-2026-DASH-{$this->seq}",
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "total_price" => 20000,
        ]);
    }

    private function todayCardValue(int $expectedAmount): string
    {
        return '<div class="value text-1_4rem">'
            . number_format($expectedAmount, 0, ".", ",") . " Mts</div>";
    }

    public function test_cashier_dashboard_counts_only_own_payments_today(): void
    {
        $a = User::factory()->create(["role" => "cashier"]);
        $this->openTill($a);
        $b = User::factory()->create(["role" => "cashier"]);
        $this->openTill($b);

        $this->actingAs($a)->post("/payments", [
            "order_id" => $this->makeOrder()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 5000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->actingAs($b)->post("/payments", [
            "order_id" => $this->makeOrder()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 3000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->assertSame($a->id, Payment::where("amount", 5000)->value("user_id"));

        $this->actingAs($a)->get("/dashboard")
            ->assertOk()
            ->assertSee($this->todayCardValue(5000), false)
            ->assertDontSee("weeklyChart", false)
            ->assertDontSee("8,000 Mts");

        $this->actingAs($b)->get("/dashboard")
            ->assertOk()
            ->assertSee($this->todayCardValue(3000), false)
            ->assertDontSee("weeklyChart", false);
    }

    public function test_admin_dashboard_still_counts_all_revenue_today(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier);

        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $this->makeOrder()->id,
            "paid_at" => now()->toDateString(),
            "amount" => 7000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get("/dashboard")
            ->assertOk()
            ->assertSee($this->todayCardValue(7000), false)
            ->assertSee("weeklyChart")
            ->assertSee("este mês 7,000 Mts");
    }

    public function test_legacy_payments_without_user_are_not_counted_for_cashier(): void
    {
        $order = $this->makeOrder();
        $cashier = User::factory()->create(["role" => "cashier"]);

        Payment::create([
            "order_id" => $order->id,
            "user_id" => null,
            "paid_at" => now()->toDateString(),
            "amount" => 4000,
            "method" => "cash",
        ]);

        $this->actingAs($cashier)->get("/dashboard")
            ->assertOk()
            ->assertSee($this->todayCardValue(0), false);
    }
}
