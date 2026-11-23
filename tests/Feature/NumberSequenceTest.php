<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Store;
use App\Support\CurrentStore;
use App\Support\NumberSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Numbered series come from a per-store counter incremented inside the saving
 * transaction: no duplicate numbers, no skipped fiscal numbers.
 */
class NumberSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_series_continues_after_the_highest_existing_number(): void
    {
        $this->assertSame(1, NumberSequence::next("T-", fn () => 0));
        $this->assertSame(2, NumberSequence::next("T-", fn () => 0));
        $this->assertSame(41, NumberSequence::next("U-", fn () => 40));
        // The seed only matters the first time.
        $this->assertSame(42, NumberSequence::next("U-", fn () => 999));
    }

    public function test_numbers_never_repeat_even_without_reading_the_records(): void
    {
        $prefix = Order::orderNumberPrefix();

        $numbers = collect(range(1, 25))->map(fn () => Order::nextOrderNumber());

        $this->assertCount(25, $numbers->unique());
        $this->assertSame($prefix . "001", $numbers->first());
        $this->assertSame($prefix . "025", $numbers->last());
    }

    public function test_a_rolled_back_save_gives_its_number_back(): void
    {
        $year = (int) now()->format("Y");

        try {
            DB::transaction(function () use ($year) {
                $this->assertSame("FT-{$year}/0001", Invoice::nextNumber($year));
                throw new RuntimeException("save failed");
            });
        } catch (RuntimeException) {
        }

        // Fiscal series must not skip a number.
        $this->assertSame("FT-{$year}/0001", Invoice::nextNumber($year));
        $this->assertSame("FT-{$year}/0002", Invoice::nextNumber($year));
    }

    public function test_every_series_has_its_own_counter(): void
    {
        $year = now()->format("Y");

        $this->assertSame("NC{$year}/0001", CreditNote::nextNumber());
        $this->assertSame("REC{$year}/0001", Complaint::nextNumber());
        $this->assertSame("FT-{$year}/0001", Invoice::nextNumber((int) $year));
        $this->assertSame("NC{$year}/0002", CreditNote::nextNumber());
    }

    public function test_each_store_numbers_its_own_records(): void
    {
        $main = Store::orderBy("id")->first();
        $second = Store::create(["name" => "Loja 2", "code" => "lj02", "is_active" => true]);
        $day = now()->format("dmY");
        $mainCode = preg_replace("/[^A-Z0-9]/", "", mb_strtoupper($main->code));

        CurrentStore::run($main, fn () => Order::nextOrderNumber());
        CurrentStore::run($main, fn () => Order::nextOrderNumber());

        $this->assertSame("ORDLJ02{$day}001", CurrentStore::run($second, fn () => Order::nextOrderNumber()));
        $this->assertSame("ORD{$mainCode}{$day}003", CurrentStore::run($main, fn () => Order::nextOrderNumber()));
    }

    public function test_order_number_is_ord_store_code_day_month_year_and_sequence(): void
    {
        $store = Store::create(["name" => "Loja Baixa", "code" => "BX01", "is_active" => true]);
        $date = \Illuminate\Support\Carbon::create(2026, 11, 5);

        $this->assertSame("ORDBX0105112026001", CurrentStore::run($store, fn () => Order::nextOrderNumber($date)));
    }

    public function test_existing_orders_seed_the_day_counter(): void
    {
        $customer = Customer::create(["name" => "Seed", "phone" => "+258840000099", "address" => "Maputo"]);
        $prefix = Order::orderNumberPrefix();
        Order::create([
            "order_number" => $prefix . "017",
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 100,
            "status" => "received",
        ]);

        $this->assertSame($prefix . "018", Order::nextOrderNumber());
    }
}
