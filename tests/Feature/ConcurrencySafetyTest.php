<?php

namespace Tests\Feature;

use App\Actions\Orders\CreateOrder;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStageLog;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use App\Services\PieceTracker;
use App\Services\ProductionReport;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Fixes from the 100-store / 100-user load test: audit chain under concurrent writers,
 * piece numbering without gap locks, store isolation of the production report and no
 * technical details in the order error message.
 */
class ConcurrencySafetyTest extends TestCase
{
    use RefreshDatabase;

    private function chainHead(): object
    {
        return DB::table(ActivityLog::CHAIN_TABLE)->where("id", 1)->first();
    }

    public function test_audit_chain_head_follows_the_last_entry(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::create(["name" => "A", "phone" => "+258840000101"]);
        Customer::create(["name" => "B", "phone" => "+258840000102"]);

        $last = DB::table("activity_logs")->orderByDesc("id")->first();
        $this->assertSame($last->id, (int) $this->chainHead()->last_id);
        $this->assertSame($last->hash, $this->chainHead()->last_hash);
        $this->assertTrue(ActivityLog::verifyChain()["ok"]);
    }

    public function test_a_rolled_back_entry_leaves_the_chain_intact(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::create(["name" => "A", "phone" => "+258840000103"]);
        $before = $this->chainHead();

        DB::beginTransaction();
        Customer::create(["name" => "Desfeito", "phone" => "+258840000104"]);
        DB::rollBack();

        $this->assertEquals($before, $this->chainHead());
        Customer::create(["name" => "C", "phone" => "+258840000105"]);
        $this->assertTrue(ActivityLog::verifyChain()["ok"]);
    }

    public function test_rehash_moves_the_chain_head(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::create(["name" => "A", "phone" => "+258840000106"]);
        DB::table(ActivityLog::CHAIN_TABLE)->where("id", 1)->update(["last_hash" => str_repeat("0", 64)]);

        ActivityLog::rehashAll();

        $this->assertSame(DB::table("activity_logs")->orderByDesc("id")->value("hash"), $this->chainHead()->last_hash);
        $this->assertTrue(ActivityLog::verifyChain()["ok"]);
    }

    public function test_pieces_added_later_continue_the_numbering(): void
    {
        $this->actingAs($user = User::factory()->create());
        $customer = Customer::create(["name" => "P", "phone" => "+258840000107"]);
        $order = Order::create(["order_number" => "ORD-CS-1", "customer_id" => $customer->id, "received_at" => today(), "total_price" => 100, "status" => "received"]);
        $tracker = app(PieceTracker::class);

        $tracker->add($order, null, 2, "Camisa", $user);
        $tracker->add($order, null, 1, "Calça", $user);

        $this->assertSame(["ORD-CS-1-01", "ORD-CS-1-02", "ORD-CS-1-03"], $order->pieces()->orderBy("seq")->pluck("code")->all());
    }

    public function test_production_report_counts_only_the_stores_own_stage_changes(): void
    {
        $main = Store::orderBy("id")->first();
        $other = Store::create(["name" => "Outra loja", "code" => "LJ09", "is_active" => true]);

        // A washing stage of one hour, in the other store only.
        CurrentStore::run($other, function () {
            $customer = Customer::create(["name" => "Outra", "phone" => "+258840000108"]);
            $order = Order::create(["order_number" => "ORD-OTHER-1", "customer_id" => $customer->id, "received_at" => today(), "total_price" => 100, "status" => "drying"]);
            OrderStageLog::create(["order_id" => $order->id, "from_status" => "received", "to_status" => "washing", "created_at" => now()->startOfDay()->addHours(8)]);
            OrderStageLog::create(["order_id" => $order->id, "from_status" => "washing", "to_status" => "drying", "created_at" => now()->startOfDay()->addHours(9)]);
        });

        $report = fn (Store $store) => CurrentStore::run($store, fn () => app(ProductionReport::class)->build(today(), today()));

        $this->assertSame(0, $report($main)["stage_times"]["stages"]["washing"]["count"]);
        $this->assertSame(1, $report($other)["stage_times"]["stages"]["washing"]["count"]);
    }

    public function test_only_chat_users_poll_the_unread_chat_count(): void
    {
        // The page polls messages.unread only when the sidebar has the chat badge.
        $this->actingAs(User::factory()->courier()->create())->get(route("courier.route"))
            ->assertOk()->assertDontSee("chatUnreadBadge", false);
        $this->get(route("messages.unread"))->assertForbidden();

        $this->actingAs(User::factory()->cashier()->create())->get(route("dashboard"))
            ->assertOk()->assertSee("chatUnreadBadge", false);
        $this->getJson(route("messages.unread"))->assertOk()->assertJsonStructure(["count"]);
    }

    public function test_a_technical_failure_creating_an_order_shows_no_sql(): void
    {
        $this->mock(CreateOrder::class)->shouldReceive("handle")
            ->andThrow(new RuntimeException("SQLSTATE[40001]: Deadlock found (SQL: insert into `order_pieces` …)"));
        $customer = Customer::create(["name" => "Erro", "phone" => "+258840000109", "address" => "Matola"]);
        $service = Service::create(["name" => "Lavagem", "type" => "by_weight", "price" => 2500, "estimated_days" => 1]);

        $response = $this->actingAs(User::factory()->create())->from("/orders/create")->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => today()->toDateString(),
            "items" => [["service_id" => $service->id, "weight" => 2]],
        ]);

        $response->assertRedirect("/orders/create")->assertSessionHas("error", __("app.order_create_failed"));
        $this->assertStringNotContainsString("SQLSTATE", session("error"));
    }
}
