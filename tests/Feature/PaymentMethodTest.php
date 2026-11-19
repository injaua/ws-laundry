<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(): Order
    {
        $customer = Customer::create(["name" => "Bila Teste", "phone" => "+258840000010", "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORD-2026-0009",
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "total_price" => 20000,
        ]);
    }

    public function test_mobile_money_methods_can_be_registered(): void
    {
        $order = $this->makeOrder();
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier);

        foreach (["mpesa", "emola", "mkesh"] as $i => $method) {
            $this->actingAs($cashier)->post("/payments", [
                "order_id" => $order->id,
                "paid_at" => now()->toDateString(),
                "amount" => 1000 + $i,
                "method" => $method,
                "transaction_ref" => "REF{$i}ABC",
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ["mpesa", "emola", "mkesh"],
            $order->payments()->orderBy("id")->pluck("method")->all()
        );
    }

    public function test_unknown_payment_method_is_rejected(): void
    {
        $order = $this->makeOrder();
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier);

        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 500,
            "method" => "paypal",
        ])->assertSessionHasErrors("method");

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_payment_greater_than_remaining_invoice_value_is_rejected(): void
    {
        $order = $this->makeOrder();
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier);

        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 25000,
            "method" => "cash",
        ])->assertSessionHasErrors("amount");

        $this->assertSame(0, $order->payments()->count());
    }

    public function test_partial_payments_accumulate_but_cannot_exceed_invoice_total(): void
    {
        $order = $this->makeOrder();
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier);

        foreach ([5000, 7000] as $amount) {
            $this->actingAs($cashier)->post("/payments", [
                "order_id" => $order->id,
                "paid_at" => now()->toDateString(),
                "amount" => $amount,
                "method" => "cash",
            ])->assertSessionHasNoErrors();
        }

        // Remaining is 8000; 9000 must be rejected.
        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 9000,
            "method" => "cash",
        ])->assertSessionHasErrors("amount");

        // Completing exactly the remaining value succeeds.
        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 8000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->assertSame(3, $order->payments()->count());
        $this->assertEquals(20000, $order->payments()->sum("amount"));
    }

    public function test_payment_on_fully_paid_order_is_blocked(): void
    {
        $order = $this->makeOrder();
        $cashier = User::factory()->create(["role" => "cashier"]);
        $this->openTill($cashier);

        foreach ([10000, 10000] as $amount) {
            $this->actingAs($cashier)->post("/payments", [
                "order_id" => $order->id,
                "paid_at" => now()->toDateString(),
                "amount" => $amount,
                "method" => "cash",
            ])->assertSessionHasNoErrors();
        }

        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 1,
            "method" => "cash",
        ])->assertRedirect()->assertSessionHas("error");

        $this->assertSame(2, $order->payments()->count());

        // Fully-paid order renders with the disabled Pagar button and form.
        $admin = User::factory()->create();
        $this->actingAs($admin)->get("/orders/" . $order->id)
            ->assertOk()
            ->assertSee("disabled", false);
        $this->actingAs($admin)->get("/orders/" . $order->id . "/payment")->assertOk();
    }

    public function test_payment_update_cannot_exceed_invoice_total(): void
    {
        $order = $this->makeOrder();
        $payment = Payment::create([
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 5000,
            "method" => "cash",
        ]);
        $admin = User::factory()->create();

        $this->actingAs($admin)->patch("/payments/" . $payment->id, [
            "paid_at" => now()->toDateString(),
            "amount" => 21000,
            "method" => "cash",
        ])->assertSessionHasErrors("amount");

        $this->assertEquals(5000, $payment->fresh()->amount);
    }

    public function test_payment_method_report_groups_totals_by_method(): void
    {
        $order = $this->makeOrder();
        Payment::create(["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 5000, "method" => "cash"]);
        Payment::create(["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 3000, "method" => "mpesa"]);
        Payment::create(["order_id" => $order->id, "paid_at" => now()->toDateString(), "amount" => 2000, "method" => "mpesa"]);

        $admin = User::factory()->create();

        $this->actingAs($admin)->get("/reports/payment-methods")
            ->assertOk()
            ->assertSee("Dinheiro")
            ->assertSee("mPesa")
            ->assertSee("10,000 Mts", false)
            ->assertSee("50%");
    }

    public function test_report_supports_date_range_filter(): void
    {
        $order = $this->makeOrder();
        Payment::create(["order_id" => $order->id, "paid_at" => now()->subMonths(3)->toDateString(), "amount" => 4000, "method" => "emola"]);

        $admin = User::factory()->create();
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->actingAs($admin)->get("/reports/payment-methods?from={$from}&to={$to}")
            ->assertOk()
            ->assertDontSee("e-Mola")
            ->assertSee("0 Mts", false);
    }

    public function test_cashier_cannot_access_payment_method_report(): void
    {
        $cashier = User::factory()->create(["role" => "cashier"]);

        $this->actingAs($cashier)->get("/reports/payment-methods")->assertForbidden();
    }
}
