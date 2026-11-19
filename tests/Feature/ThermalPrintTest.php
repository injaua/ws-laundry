<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThermalPrintTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithPayment(): array
    {
        $customer = Customer::create(["name" => "Maria Teste", "phone" => "+258840000009", "address" => "Matola"]);
        $order = Order::create([
            "order_number" => "ORD-2026-0001",
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "total_price" => 15000,
        ]);
        $payment = Payment::create([
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 5000,
            "method" => "cash",
        ]);

        return [$order, $payment];
    }

    public function test_receipt_is_visible_to_cashier_and_contains_number(): void
    {
        [, $payment] = $this->orderWithPayment();
        $cashier = User::factory()->create(["role" => "cashier"]);

        $this->actingAs($cashier)->get("/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertSee(sprintf("REC-%06d", $payment->id))
            ->assertSee("5,000 Mts", false)
            ->assertSee("Maria Teste");
    }

    public function test_receipt_requires_authentication(): void
    {
        [, $payment] = $this->orderWithPayment();

        $this->get("/payments/{$payment->id}/receipt")->assertRedirect("/login");
    }

    public function test_invoice_defaults_to_80mm_and_accepts_58mm(): void
    {
        [$order] = $this->orderWithPayment();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get("/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertSee("class=\"ticket-80\"", false);

        $this->actingAs($admin)->get("/orders/{$order->id}/invoice?w=58")
            ->assertOk()
            ->assertSee("class=\"ticket-58\"", false);

        $this->actingAs($admin)->get("/orders/{$order->id}/invoice?w=99")
            ->assertOk()
            ->assertSee("class=\"ticket-80\"", false);
    }

    public function test_label_supports_thermal_mode(): void
    {
        [$order] = $this->orderWithPayment();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get("/orders/{$order->id}/label?type=thermal")
            ->assertOk()
            ->assertSee("class=\"label-thermal\"", false);

        $this->actingAs($admin)->get("/orders/{$order->id}/label")
            ->assertOk()
            ->assertSee("class=\"label-a4\"", false);
    }
}
