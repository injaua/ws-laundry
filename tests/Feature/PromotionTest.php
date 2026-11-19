<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerPrice;
use App\Models\Order;
use App\Models\Package;
use App\Models\Promotion;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    private Service $byWeight;

    private Service $byPiece;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();
        SiteSetting::set("vat_rate", "0");

        $this->admin = User::factory()->create();
        $this->customer = Customer::create(["name" => "Maria", "phone" => "+258841110000"]);
        $this->byWeight = Service::create(["name" => "Lavar e secar", "type" => "by_weight", "price" => 100, "estimated_days" => 3]);
        $this->byPiece = Service::create(["name" => "Camisa", "type" => "by_piece", "price" => 50, "estimated_days" => 2]);
        $this->package = Package::create(["name" => "Pacote 8kg", "weight_kg" => 8, "price" => 1000, "is_active" => true]);
    }

    private function promotion(array $attributes, array $services = [], array $packages = []): Promotion
    {
        $promotion = Promotion::create(array_merge([
            "name" => "Promo",
            "discount_type" => "percent",
            "value" => 20,
            "applies_to" => $services || $packages ? "selected" : "all",
            "starts_on" => "2026-12-01",
            "ends_on" => "2026-12-31",
            "is_active" => true,
        ], $attributes));

        foreach ($services as $service) {
            $promotion->items()->create(["service_id" => $service->id]);
        }
        foreach ($packages as $package) {
            $promotion->items()->create(["package_id" => $package->id]);
        }

        return $promotion;
    }

    private function order(string $date, array $extra = []): Order
    {
        $this->actingAs($this->admin)->post("/orders", array_merge([
            "customer_id" => $this->customer->id,
            "received_at" => $date,
            "items" => [
                ["service_id" => $this->byWeight->id, "weight" => 4],
                ["service_id" => $this->byPiece->id, "amount" => 3],
                ["package_id" => $this->package->id, "amount" => 1],
            ],
        ], $extra))->assertSessionHasNoErrors()->assertRedirect();

        return Order::with("items")->latest("id")->first();
    }

    public function test_percent_promotion_applies_per_kg_per_piece_and_per_package(): void
    {
        $promotion = $this->promotion(["name" => "Natal"]);

        $order = $this->order("2026-12-10");

        $this->assertSame([80.0, 40.0, 800.0], $order->items->map(fn ($i) => (float) $i->unit_price)->all());
        $this->assertSame([100.0, 50.0, 1000.0], $order->items->map(fn ($i) => (float) $i->list_price)->all());
        $this->assertTrue($order->items->every(fn ($i) => $i->promotion_id === $promotion->id));
        $this->assertSame(320 + 120 + 800.0, (float) $order->total_price);
        $this->assertSame(80.0, $order->items[0]->discountAmount()); // 20 Mts × 4 kg
    }

    public function test_fixed_promotion_is_per_unit_and_limited_to_selected_items_and_dates(): void
    {
        $this->promotion(["discount_type" => "fixed", "value" => 30], [$this->byWeight]);

        $inside = $this->order("2026-12-31");
        $this->assertSame(70.0, (float) $inside->items[0]->unit_price);   // 100 − 30 per kg
        $this->assertSame(50.0, (float) $inside->items[1]->unit_price);   // not selected
        $this->assertNull($inside->items[1]->promotion_id);

        $after = $this->order("2027-01-01");
        $this->assertSame(100.0, (float) $after->items[0]->unit_price);
        $this->assertNull($after->items[0]->promotion_id);
    }

    public function test_weekday_restriction_and_inactive_promotions(): void
    {
        // 2026-12-09 is a Wednesday (3), 2026-12-10 a Thursday.
        $this->promotion(["weekdays" => [3]]);
        $this->promotion(["value" => 90, "is_active" => false]);

        $this->assertSame(80.0, (float) $this->order("2026-12-09")->items[0]->unit_price);
        $this->assertSame(100.0, (float) $this->order("2026-12-10")->items[0]->unit_price);
    }

    public function test_best_promotion_wins_and_never_stacks_with_customer_price(): void
    {
        $this->promotion(["value" => 10]);
        $best = $this->promotion(["value" => 25], [$this->byWeight]);

        // Per kg: negotiated 70 beats the 25% promo (75). Per piece: customer −15% beats the 10% promo.
        $this->customer->update(["discount_percent" => 15]);
        CustomerPrice::create(["customer_id" => $this->customer->id, "service_id" => $this->byWeight->id, "price" => 70]);

        $order = $this->order("2026-12-15");

        $this->assertSame(70.0, (float) $order->items[0]->unit_price);     // negotiated, no promotion
        $this->assertNull($order->items[0]->promotion_id);
        $this->assertSame(42.5, (float) $order->items[1]->unit_price);     // customer −15% beats promo −10%
        $this->assertNull($order->items[1]->promotion_id);

        $this->customer->prices()->delete();
        $this->customer->update(["discount_percent" => 0]);
        $order = $this->order("2026-12-15");
        $this->assertSame(75.0, (float) $order->items[0]->unit_price);     // best promotion, not 10% then 25%
        $this->assertSame($best->id, $order->items[0]->promotion_id);
    }

    public function test_express_surcharge_is_added_before_vat_and_sets_delivery(): void
    {
        SiteSetting::set("vat_rate", "16");
        SiteSetting::set("express_enabled", "1");
        SiteSetting::set("express_surcharge_type", "percent");
        SiteSetting::set("express_surcharge_value", "50");
        SiteSetting::set("express_lead_days", "0");

        $order = $this->order("2026-12-10", ["is_express" => 1]);

        $items = 400 + 150 + 1000.0;
        $this->assertTrue($order->is_express);
        $this->assertSame(775.0, (float) $order->express_fee);
        $this->assertSame($items + 775, (float) $order->subtotal);
        $this->assertSame(round(($items + 775) * 1.16), (float) $order->total_price);
        $this->assertSame("2026-12-10", $order->delivery_date->toDateString());

        $this->actingAs($this->admin)->get(route("orders.show", $order))->assertOk()->assertSee(__("app.express_fee"));
        $this->actingAs($this->admin)->get(route("orders.invoice", $order))->assertOk()->assertSee("775");

        // Fixed fee per order; ignored when the service is switched off.
        SiteSetting::set("express_surcharge_type", "fixed");
        SiteSetting::set("express_surcharge_value", "200");
        $this->assertSame(200.0, (float) $this->order("2026-12-10", ["is_express" => 1])->express_fee);

        SiteSetting::set("express_enabled", "0");
        $plain = $this->order("2026-12-10", ["is_express" => 1]);
        $this->assertFalse($plain->is_express);
        $this->assertSame(0.0, (float) $plain->express_fee);
        $this->assertSame("2026-12-13", $plain->delivery_date->toDateString()); // longest lead time (3 days)
    }

    public function test_promotion_crud_and_deactivation_when_used(): void
    {
        $this->actingAs($this->admin)->post(route("promotions.store"), [
            "name" => "Engomadoria", "discount_type" => "percent", "value" => 150, "applies_to" => "all", "starts_on" => "2026-12-01",
        ])->assertSessionHasErrors("value");

        $this->actingAs($this->admin)->post(route("promotions.store"), [
            "name" => "Engomadoria", "discount_type" => "percent", "value" => 20, "applies_to" => "selected", "starts_on" => "2026-12-01",
        ])->assertSessionHasErrors("services");

        $this->actingAs($this->admin)->post(route("promotions.store"), [
            "name" => "Engomadoria", "discount_type" => "percent", "value" => 20, "applies_to" => "selected",
            "services" => [$this->byPiece->id], "packages" => [$this->package->id],
            "starts_on" => "2026-12-01", "ends_on" => "2026-12-31", "weekdays" => [1, 2, 3, 4, 5, 6, 0], "is_active" => 1,
        ])->assertRedirect(route("promotions.index"));

        $promotion = Promotion::with("items")->firstOrFail();
        $this->assertNull($promotion->weekdays);  // every day = no restriction
        $this->assertCount(2, $promotion->items);

        $this->order("2026-12-05");
        $this->actingAs($this->admin)->get(route("promotions.index"))->assertOk()->assertSee("Engomadoria")->assertSee("−20%");
        $this->actingAs($this->admin)->get(route("promotions.edit", $promotion))->assertOk();
        $this->actingAs($this->admin)->get(route("orders.create"))->assertOk()->assertSee('"name":"Engomadoria"', false);

        $this->actingAs($this->admin)->delete(route("promotions.destroy", $promotion))->assertSessionHas("success", __("app.promotion_deactivated"));
        $this->assertFalse($promotion->fresh()->is_active);

        $unused = $this->promotion(["name" => "Teste"]);
        $this->actingAs($this->admin)->delete(route("promotions.destroy", $unused));
        $this->assertModelMissing($unused);
    }

    public function test_cashier_cannot_manage_promotions_and_express_settings_save(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get(route("promotions.index"))->assertForbidden();

        $this->actingAs($this->admin)->post(route("admin.business-settings.update"), [
            "vat_rate" => 16, "express_enabled" => 1, "express_surcharge_type" => "fixed", "express_surcharge_value" => 150, "express_lead_days" => 1,
        ])->assertRedirect();

        $this->assertSame(["enabled" => true, "type" => "fixed", "value" => 150.0, "days" => 1], Pricing::expressSettings());
    }
}
