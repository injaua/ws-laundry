<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ManagementIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->customer = Customer::create(["name" => "Ana", "phone" => "+258841234567", "address" => "Matola"]);
    }

    private function order(string $number, float $subtotal, string $date, array $extra = []): Order
    {
        return Order::create(array_merge([
            "order_number" => $number, "customer_id" => $this->customer->id, "status" => "received", "received_at" => $date,
            "subtotal" => $subtotal, "vat_rate" => 0, "vat_amount" => 0, "total_price" => $subtotal,
        ], $extra));
    }

    public function test_kpis_against_previous_period_and_targets(): void
    {
        $this->order("KP-1", 1000, today()->toDateString());
        $this->order("KP-2", 0, today()->toDateString(), ["rework_of_order_id" => null, "is_express" => true]);
        Expense::create(["title" => "Luz", "category" => "electricity", "period_type" => "monthly", "amount" => 400, "expense_date" => today()->toDateString(), "user_id" => $this->admin->id]);
        SiteSetting::set("kpi_targets", json_encode(["operating_margin" => 50, "expenses" => 300]));

        $data = app(ManagementIndicators::class)->build(today()->subDays(6), today());
        $kpi = $data["indicators"];

        $this->assertEquals(1000, $kpi["revenue"]["value"]);
        $this->assertEquals(400, $kpi["expenses"]["value"]);
        $this->assertEquals(600, $kpi["operating_result"]["value"]);
        $this->assertEquals(60.0, $kpi["operating_margin"]["value"]);
        $this->assertSame("good", $kpi["operating_margin"]["status"]);
        $this->assertSame("bad", $kpi["expenses"]["status"]);
        $this->assertEquals(2, $kpi["orders"]["value"]);
        $this->assertEquals(50.0, $kpi["express_share"]["value"]);
        $this->assertEquals(1, $kpi["active_customers"]["value"]);
        $this->assertEquals(0, $kpi["revenue"]["previous"]);
    }

    public function test_returning_customers_and_trend(): void
    {
        $this->order("KP-OLD", 500, today()->subDays(10)->toDateString());
        $this->order("KP-NEW", 700, today()->toDateString());

        $data = app(ManagementIndicators::class)->build(today()->subDays(6), today());
        $this->assertEquals(100.0, $data["indicators"]["returning_rate"]["value"]);
        $this->assertEquals(500, $data["indicators"]["revenue"]["previous"]);
        $this->assertEquals(40.0, $data["indicators"]["revenue"]["change"]);
        $this->assertTrue($data["indicators"]["revenue"]["trend"]["good"]);

        $trend = app(ManagementIndicators::class)->monthlyTrend(12);
        $this->assertCount(12, $trend);
        $this->assertSame(today()->format("Y-m"), end($trend)["month"]);
    }

    public function test_pages_targets_export_and_access(): void
    {
        $this->order("KP-1", 1000, today()->toDateString());

        $this->actingAs($this->admin)->get(route("management.index"))->assertOk()->assertSee(__("app.kpi_revenue"));
        foreach (["last_month", "last_30_days", "this_quarter", "this_year"] as $period) {
            $this->actingAs($this->admin)->get(route("management.index", ["period" => $period]))->assertOk();
        }
        $this->actingAs($this->admin)->get(route("management.index", ["period" => "custom", "from" => today()->toDateString(), "to" => today()->subMonth()->toDateString()]))->assertOk();

        $this->actingAs($this->admin)->get(route("management.targets"))->assertOk();
        $this->actingAs($this->admin)->put(route("management.targets.save"), ["targets" => ["revenue" => 5000, "orders" => "", "invented" => 3]])
            ->assertRedirect(route("management.index"));
        $this->assertSame(["revenue" => 5000.0], ManagementIndicators::targets());

        $csv = $this->actingAs($this->admin)->get(route("management.export"))->assertOk()->streamedContent();
        $this->assertStringContainsString(__("app.kpi_revenue"), $csv);

        $this->actingAs(User::factory()->owner()->create())->get(route("management.index"))->assertOk();
        $this->actingAs(User::factory()->cashier()->create())->get(route("management.index"))->assertForbidden();
    }
}
