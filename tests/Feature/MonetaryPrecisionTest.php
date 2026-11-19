<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MobileMoney\MobilePayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every amount is kept to the cent, from the counter to the ledger. */
class MonetaryPrecisionTest extends TestCase
{
    use RefreshDatabase;

    private function order(float $total): Order
    {
        $customer = Customer::create(["name" => "Cliente Centavos", "phone" => "+258840000099", "address" => "Maputo"]);

        return Order::create([
            "order_number" => "ORD-2026-0099",
            "customer_id" => $customer->id,
            "status" => "washing",
            "received_at" => now()->toDateString(),
            "total_price" => $total,
        ]);
    }

    public function test_payment_with_cents_is_posted_to_the_journal_with_cents(): void
    {
        $order = $this->order(1000);
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->toDateString(),
            "amount" => 150.55,
            "method" => "cash",
        ])->assertSessionHasNoErrors();

        $this->assertSame("150.55", $order->payments()->first()->amount);
        $this->assertEqualsWithDelta(150.55, (float) JournalLine::sum("debit"), 0.001);
        $this->assertEqualsWithDelta(150.55, (float) JournalLine::sum("credit"), 0.001);
        $this->assertEqualsWithDelta(849.45, $order->fresh()->remainingBalance(), 0.001);
    }

    public function test_expense_with_vat_splits_to_the_cent_and_stays_balanced(): void
    {
        SiteSetting::set("vat_rate", 16);
        ExpenseCategory::create([
            "key" => "agua",
            "name" => "Água",
            "color" => "info",
            "account_id" => Account::findByCode("6.3.2.9.9")->id,
            "is_vat_applicable" => true,
        ]);

        $this->actingAs(User::factory()->create())->post("/expenses", [
            "title" => "Conta de água",
            "category" => "agua",
            "amount" => 1234.56,
            "expense_date" => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $entry = JournalEntry::firstWhere("source", "expense");
        $this->assertNotNull($entry);
        $lines = $entry->lines;
        $this->assertEqualsWithDelta(1234.56, (float) $lines->sum("credit"), 0.001);
        $this->assertEqualsWithDelta(1234.56, (float) $lines->sum("debit"), 0.001);
        // 1234.56 × 16 / 116 = 170.28 VAT, 1064.28 net
        $this->assertEqualsWithDelta(170.28, (float) $lines->firstWhere("account_id", Account::findByCode("4.4.3.1")->id)->debit, 0.001);
    }

    public function test_net_salary_keeps_cents(): void
    {
        $employee = Employee::factory()->create(["base_salary" => 15000.50]);

        $salary = EmployeeSalary::factory()->create([
            "employee_id" => $employee->id,
            "base_salary" => 15000.50,
            "bonus" => 250.25,
            "deduction" => 100.10,
            "absence_deduction" => 0,
        ]);

        $this->assertSame("15150.65", $salary->fresh()->net_salary);
    }

    public function test_recalculated_order_vat_is_rounded_to_the_cent(): void
    {
        $order = $this->order(0);
        $order->update(["vat_rate" => 16, "express_fee" => 10.33]);

        $order->recalculateTotals();
        $order->refresh();

        // 10.33 × 16% = 1.6528 → 1.65
        $this->assertSame("1.65", $order->vat_amount);
        $this->assertSame("11.98", $order->total_price);
    }

    public function test_mobile_wallets_only_take_whole_amounts(): void
    {
        $this->assertTrue(MobilePayments::isWholeAmount(150));
        $this->assertTrue(MobilePayments::isWholeAmount("150.00"));
        $this->assertFalse(MobilePayments::isWholeAmount(150.5));
    }
}
