<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function byWeightService(float $price): Service
    {
        return Service::create(["name" => "Lavanda", "type" => "by_weight", "price" => $price, "estimated_days" => 2]);
    }

    private function createOrder(): Order
    {
        static $sequence = 0;
        $service = $this->byWeightService(1000);
        $customer = Customer::create(["name" => "Iva Cliente", "phone" => "+258840000" . str_pad((string) ++$sequence, 3, "0", STR_PAD_LEFT), "address" => "Matola"]);

        $this->actingAs($this->admin())->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->format("Y-m-d"),
            "total_price" => 1,
            "items" => [
                ["service_id" => $service->id, "weight" => 2, "price" => 1, "subtotal" => 1],
            ],
        ])->assertRedirect();

        return Order::latest("id")->first();
    }

    public function test_store_ignores_client_prices_and_uses_catalog_plus_iva(): void
    {
        $order = $this->createOrder();

        $this->assertEquals(2000, (float) $order->subtotal);
        $this->assertEquals(16, (float) $order->vat_rate);
        $this->assertEquals(320, (float) $order->vat_amount);
        $this->assertEquals(2320, (float) $order->total_price);

        $item = $order->items()->first();
        $this->assertEquals(1000, (float) $item->unit_price);
        $this->assertEquals(2000, (float) $item->subtotal);
    }

    public function test_rate_change_affects_new_orders_only(): void
    {
        $old = $this->createOrder();

        SiteSetting::set("vat_rate", "10");
        $new = $this->createOrder();

        $this->assertEquals(320, (float) $old->vat_amount);
        $this->assertEquals(10, (float) $new->vat_rate);
        $this->assertEquals(200, (float) $new->vat_amount);
        $this->assertEquals(2200, (float) $new->total_price);
    }

    public function test_admin_can_update_iva_rate_and_invalid_values_are_rejected(): void
    {
        $this->actingAs($this->admin())->post("/admin/business-settings", [
            "vat_rate" => "17",
        ])->assertSessionHasNoErrors();
        $this->assertEquals("17", SiteSetting::get("vat_rate"));

        $this->actingAs($this->admin())->post("/admin/business-settings", [
            "vat_rate" => "150",
        ])->assertSessionHasErrors("vat_rate");
        $this->assertEquals("17", SiteSetting::get("vat_rate"));
    }

    public function test_invoice_and_receipt_show_iva_breakdown(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => 2320,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->admin())->get("/orders/{$order->id}/invoice")
            ->assertOk()
            ->assertSee("IVA 16%");

        $payment = $order->payments()->first();
        $this->actingAs($this->admin())->get("/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertSee("IVA 16%")
            ->assertSee("320");
    }

    public function test_payment_journal_splits_revenue_and_vat(): void
    {
        $order = $this->createOrder();

        $this->actingAs($this->admin())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => 2320,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "payment");
        $this->assertNotNull($entry);
        $this->assertSame(3, $entry->lines()->count());

        $lines = $entry->lines()->get()->keyBy("account_id");
        $this->assertEquals(2320, (float) $lines[Account::findByCode("1.1")->id]->debit);
        $this->assertEquals(2000, (float) $lines[Account::findByCode("7.2")->id]->credit);
        $this->assertEquals(320, (float) $lines[Account::findByCode("4.4.3.3")->id]->credit);
    }

    public function test_zero_iva_rate_keeps_old_behaviour(): void
    {
        SiteSetting::set("vat_rate", "0");
        $order = $this->createOrder();

        $this->assertEquals(2000, (float) $order->subtotal);
        $this->assertEquals(0, (float) $order->vat_amount);
        $this->assertEquals(2000, (float) $order->total_price);

        $this->actingAs($this->admin())->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => 2000,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "payment");
        $this->assertSame(2, $entry->lines()->count());
        $this->assertEquals(2000, (float) $entry->lines()->where("credit", ">", 0)->first()->credit);
        $this->assertEquals("7.2", Account::find($entry->lines()->where("credit", ">", 0)->first()->account_id)->code);
    }

    public function test_cashier_cannot_access_statement_pages(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->post("/admin/business-settings", [
            "vat_rate" => "20",
        ])->assertForbidden();
    }
}
