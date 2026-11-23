<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFilterAndOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_search_by_number_respects_status_filter(): void
    {
        $customer = Customer::create(["name" => "Maria Search", "phone" => "+258840000010", "address" => "Maputo"]);
        $this->order($customer, "ORDTESTREC", "received");
        $this->order($customer, "ORDTESTRDY", "ready");

        $response = $this->actingAs(User::factory()->create())
            ->get("/orders?search=ORDTEST&status=ready");

        $response->assertOk()
            ->assertSee("ORDTESTRDY")
            ->assertDontSee("ORDTESTREC");
    }

    public function test_expense_search_by_title_respects_category_filter(): void
    {
        $user = User::factory()->create();
        $this->expense($user, "Sabao liquido", "detergente", "supplies");
        $this->expense($user, "Sabao renda", "renda da loja", "rent");

        $response = $this->actingAs($user)->get("/expenses?search=sabao&category=supplies");

        $response->assertOk()
            ->assertSee("Sabao liquido")
            ->assertDontSee("Sabao renda");
    }

    public function test_order_number_follows_highest_sequence_not_row_count(): void
    {
        $customer = Customer::create(["name" => "Seq", "phone" => "+258840000011", "address" => "Matola"]);
        $prefix = Order::orderNumberPrefix();

        // A gap (e.g. a force-deleted order) used to make count()+1 reuse 002.
        $this->order($customer, $prefix . "003", "received");
        $deleted = $this->order($customer, $prefix . "005", "received");
        $deleted->delete();

        $this->assertSame($prefix . "006", Order::nextOrderNumber());
    }

    public function test_order_number_rolls_past_999_without_collision(): void
    {
        $customer = Customer::create(["name" => "Big Day", "phone" => "+258840000012", "address" => "Matola"]);
        $prefix = Order::orderNumberPrefix();
        $this->order($customer, $prefix . "999", "received");

        $this->assertSame($prefix . "1000", Order::nextOrderNumber());
    }

    public function test_store_skips_numbers_already_taken(): void
    {
        $customer = Customer::create(["name" => "Store", "phone" => "+258840000013", "address" => "Matola"]);
        $service = Service::create(["name" => "Lavar", "type" => "by_piece", "price" => 100, "estimated_days" => 1]);
        $prefix = Order::orderNumberPrefix();
        $this->order($customer, $prefix . "001", "received");

        $this->actingAs(User::factory()->create())->post("/orders", [
            "customer_id" => $customer->id,
            "received_at" => now()->toDateString(),
            "items" => [["service_id" => $service->id, "amount" => 2]],
        ])->assertRedirect();

        $this->assertDatabaseHas("orders", ["order_number" => $prefix . "002"]);
    }

    private function order(Customer $customer, string $number, string $status): Order
    {
        return Order::create([
            "order_number" => $number,
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 1000,
            "status" => $status,
        ]);
    }

    private function expense(User $user, string $title, string $description, string $category): Expense
    {
        return Expense::create([
            "title" => $title,
            "description" => $description,
            "category" => $category,
            "amount" => 500,
            "expense_date" => now()->toDateString(),
            "user_id" => $user->id,
        ]);
    }
}
