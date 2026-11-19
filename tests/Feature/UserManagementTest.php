<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_admin_user(): void
    {
        $response = $this->post("/register", [
            "name" => "First Admin",
            "email" => "first@example.com",
            "password" => "Password2026",
            "password_confirmation" => "Password2026",
        ]);

        $user = User::where("email", "first@example.com")->first();
        $this->assertNotNull($user);
        $this->assertSame("admin", $user->role);
        $response->assertRedirect("/dashboard");
    }

    public function test_registration_is_open_only_until_first_admin_exists(): void
    {
        $this->get("/login")->assertOk()->assertSee(__('app.register'), false);
        $this->get("/register")->assertOk();

        User::factory()->create(); // first admin

        $this->get("/login")->assertOk()->assertDontSee(__('app.register'), false);
        $this->get("/register")->assertRedirect("/login")->assertSessionHas("error");
        $this->post("/register", [
            "name" => "Second Admin",
            "email" => "second@example.com",
            "password" => "Password2026",
            "password_confirmation" => "Password2026",
        ])->assertForbidden();
        $this->assertDatabaseMissing("users", ["email" => "second@example.com"]);
    }

    public function test_admin_can_create_owner_and_cashier(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post("/users", [
            "name" => "The Owner",
            "email" => "owner@test.local",
            "password" => "Password2026",
            "password_confirmation" => "Password2026",
            "role" => "owner",
        ])->assertRedirect(route("users.index"));

        $this->actingAs($admin)->post("/users", [
            "name" => "The Store",
            "email" => "store@test.local",
            "password" => "Password2026",
            "password_confirmation" => "Password2026",
            "role" => "cashier",
        ])->assertRedirect(route("users.index"));

        $this->assertDatabaseHas("users", ["email" => "owner@test.local", "role" => "owner"]);
        $this->assertDatabaseHas("users", ["email" => "store@test.local", "role" => "cashier"]);
    }

    public function test_cashier_cannot_access_users_and_owner_cannot_edit(): void
    {
        // A cashier of another store: the owner cannot manage it.
        $cashier = User::factory()->cashier()->create(["store_id" => Store::create(["name" => "Outra loja", "code" => "LJ02"])->id]);
        $owner = User::factory()->owner()->create();

        $this->actingAs($cashier)->get("/users")->assertForbidden();
        $this->actingAs($cashier)->post("/users", [])->assertForbidden();
        $this->actingAs($owner)->get("/users")->assertOk();
        $this->actingAs($owner)->get("/users/{$cashier->id}/edit")->assertForbidden();
        $this->actingAs($owner)->delete("/users/{$cashier->id}")->assertForbidden();
    }

    public function test_owner_can_create_cashier_but_not_admin_or_owner(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post("/users", [
            "name" => "New Cashier",
            "email" => "cashier2@test.local",
            "password" => "Password2026",
            "password_confirmation" => "Password2026",
            "role" => "cashier",
        ])->assertRedirect(route("users.index"));
        $this->assertDatabaseHas("users", ["email" => "cashier2@test.local", "role" => "cashier"]);

        foreach (["admin", "owner"] as $role) {
            $this->actingAs($owner)->post("/users", [
                "name" => "Sneaky $role",
                "email" => "$role-sneaky@test.local",
                "password" => "Password2026",
                "password_confirmation" => "Password2026",
                "role" => $role,
            ])->assertSessionHasErrors("role");
            $this->assertDatabaseMissing("users", ["email" => "$role-sneaky@test.local"]);
        }
    }

    public function test_owner_can_edit_and_delete_only_their_store_cashiers(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post("/users", [
            "name" => "My Cashier",
            "email" => "mine@test.local",
            "password" => "Password2026",
            "password_confirmation" => "Password2026",
            "role" => "cashier",
        ])->assertRedirect(route("users.index"));
        $cashier = User::where("email", "mine@test.local")->first();
        $this->assertSame($owner->id, (int) $cashier->created_by);

        $this->actingAs($owner)->get("/users/{$cashier->id}/edit")->assertOk();
        $this->actingAs($owner)->patch("/users/{$cashier->id}", [
            "name" => "Renamed Cashier",
            "email" => "mine@test.local",
            "role" => "cashier",
            "is_active" => 1,
        ])->assertRedirect(route("users.index"));
        $this->assertSame("Renamed Cashier", $cashier->fresh()->name);

        // a cashier of another store is out of the owner's reach
        $foreign = User::factory()->cashier()->create(["email" => "foreign@test.local", "store_id" => Store::create(["name" => "Outra loja", "code" => "LJ02"])->id]);
        $this->actingAs($owner)->get("/users/{$foreign->id}/edit")->assertForbidden();
        $this->actingAs($owner)->patch("/users/{$foreign->id}", ["name" => "x", "email" => "foreign@test.local", "role" => "cashier"])->assertForbidden();
        $this->actingAs($owner)->delete("/users/{$foreign->id}")->assertForbidden();

        $this->actingAs($owner)->delete("/users/{$cashier->id}")->assertRedirect(route("users.index"));
        $this->assertDatabaseMissing("users", ["id" => $cashier->id]);
    }

    public function test_owner_user_list_shows_only_cashiers(): void
    {
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->create(["email" => "boss@test.local"]);
        $cashier = User::factory()->cashier()->create(["email" => "shop@test.local"]);

        $this->actingAs($owner)->get("/users")
            ->assertOk()
            ->assertSee("shop@test.local")
            ->assertDontSee("boss@test.local");
    }

    public function test_admin_cannot_delete_or_deactivate_self(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->delete("/users/$admin->id")
            ->assertRedirect(route("users.index"));
        $this->assertDatabaseHas("users", ["id" => $admin->id]);

        $this->actingAs($admin)->patch("/users/$admin->id/toggle-active")
            ->assertSessionHas("error");
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_self_demotion_is_blocked(): void
    {
        $admin = User::factory()->create();
        User::factory()->create(); // second active admin exists

        $this->actingAs($admin)->patch("/users/$admin->id", [
            "name" => $admin->name,
            "email" => $admin->email,
            "role" => "owner",
            "is_active" => 1,
        ])->assertSessionHas("error");

        $this->assertSame("admin", $admin->fresh()->role);
    }

    public function test_is_last_active_admin_helper(): void
    {
        $admin = User::factory()->create();

        $this->assertTrue($admin->isLastActiveAdmin());

        User::factory()->create();
        $this->assertFalse($admin->fresh()->isLastActiveAdmin());
    }

    public function test_deactivated_user_is_logged_out_and_cannot_relogin(): void
    {
        $admin = User::factory()->create();
        $victim = User::factory()->cashier()->create();

        $this->actingAs($victim)->get("/dashboard")->assertOk();

        $this->actingAs($admin)->patch("/users/{$victim->id}/toggle-active")
            ->assertSessionHas("success");

        $this->assertFalse($victim->fresh()->is_active);

        $this->actingAs($victim->fresh())->get("/dashboard")
            ->assertRedirect("/login");

        $this->post("/login", [
            "email" => $victim->email,
            "password" => "password",
        ])->assertSessionHasErrors("email");
    }

    public function test_owner_can_access_orders_customers_and_reports(): void
    {
        $owner = User::factory()->owner()->create();
        $order = $this->makeOrder();

        $this->actingAs($owner)->get("/orders")->assertOk();
        $this->actingAs($owner)->get("/customers")->assertOk();
        $this->actingAs($owner)->get("/reports")->assertOk();
        $this->actingAs($owner)->get("/services")->assertOk();
        $this->actingAs($owner)->get("/users")->assertOk();

        // owner keeps management actions
        $this->actingAs($owner)->get("/orders/{$order->id}/edit")->assertOk();
        $this->actingAs($owner)->get("/orders/trash")->assertOk();
    }

    public function test_owner_accesses_expenses_but_not_the_company_website(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get("/expenses")->assertOk();
        $this->actingAs($owner)->get("/expenses/create")->assertOk();
        $this->actingAs($owner)->post("/expenses", [
            "title" => "Detergente",
            "category" => "detergent",
            "amount" => 1500,
            "expense_date" => now()->format("Y-m-d"),
        ])->assertRedirect();
        $this->assertDatabaseHas("expenses", ["title" => "Detergente"]);

        // The public website is the same for every store: admin only.
        $this->actingAs($owner)->get("/admin/landing-settings")->assertForbidden();
        $this->actingAs($owner)->get("/admin/testimonials")->assertForbidden();
        $this->actingAs($owner)->get("/admin/galleries")->assertForbidden();

        // The store's catalogue: the owner manages services and packages; system settings stay admin-only.
        $this->actingAs($owner)->get("/services")->assertOk();
        $this->actingAs($owner)->get("/packages")->assertOk();
        $this->actingAs($owner)->get("/admin/system-settings/general")->assertForbidden();

        $cashier = User::factory()->cashier()->create();
        $this->actingAs($cashier)->get("/expenses")->assertForbidden();
        $this->actingAs($cashier)->get("/admin/landing-settings")->assertForbidden();
        $this->actingAs($cashier)->get("/admin/testimonials")->assertForbidden();
        $this->actingAs($cashier)->get("/admin/galleries")->assertForbidden();
    }

    public function test_cashier_permissions(): void
    {
        $cashier = User::factory()->cashier()->create();

        $this->actingAs($cashier)->get("/orders")->assertOk();
        $this->actingAs($cashier)->get("/reports")->assertForbidden();
        $this->actingAs($cashier)->get("/services")->assertForbidden();
    }

    public function test_cashier_can_view_and_create_but_not_manage_records(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->makeOrder();
        $payment = Payment::create([
            "order_id" => $order->id,
            "paid_at" => now(),
            "amount" => 1000,
            "method" => "cash",
        ]);

        // view + create pages stay open
        $this->actingAs($cashier)->get("/orders/create")->assertOk();
        $this->actingAs($cashier)->get("/customers/create")->assertOk();
        $this->actingAs($cashier)->get("/orders/{$order->id}")->assertOk();
        $this->actingAs($cashier)->get("/orders/{$order->id}/payment")->assertOk();
        $this->actingAs($cashier)->get("/orders/{$order->id}/invoice")->assertOk();
        $this->actingAs($cashier)->get("/orders/{$order->id}/label")->assertOk();

        // management actions are blocked
        $this->actingAs($cashier)->get("/orders/{$order->id}/edit")->assertForbidden();
        $this->actingAs($cashier)->delete("/orders/{$order->id}")->assertForbidden();
        $this->actingAs($cashier)->get("/orders/trash")->assertForbidden();
        $this->actingAs($cashier)->patch("/orders/{$order->id}/restore")->assertForbidden();
        $this->actingAs($cashier)->get("/customers/{$order->customer_id}/edit")->assertForbidden();
        $this->actingAs($cashier)->put("/customers/{$order->customer_id}", ["name" => "x", "phone" => "+258000000000", "address" => "y"])->assertForbidden();
        $this->actingAs($cashier)->delete("/customers/{$order->customer_id}")->assertForbidden();
        $this->actingAs($cashier)->patch("/payments/{$payment->id}", ["amount" => 5])->assertForbidden();
        $this->actingAs($cashier)->delete("/payments/{$payment->id}")->assertForbidden();
    }

    public function test_cashier_can_update_status_but_not_order_data(): void
    {
        $cashier = User::factory()->cashier()->create();
        $order = $this->makeOrder();

        $this->actingAs($cashier)->put("/orders/{$order->id}", [
            "customer_id" => $order->customer_id,
            "status" => "washing",
            "received_at" => $order->received_at->format("Y-m-d"),
        ])->assertRedirect();

        $this->assertSame("washing", $order->fresh()->status);

        // attempting to move the order to another customer is rejected
        $otherCustomer = Customer::create(["name" => "Other", "phone" => "+258840000002", "address" => "Somewhere"]);
        $this->actingAs($cashier)->put("/orders/{$order->id}", [
            "customer_id" => $otherCustomer->id,
            "status" => "ready",
            "received_at" => $order->received_at->format("Y-m-d"),
        ])->assertSessionHas("error");

        $fresh = $order->fresh();
        $this->assertSame("washing", $fresh->status);
        $this->assertSame($order->customer_id, $fresh->customer_id);
    }

    public function test_cashier_can_create_payment(): void
    {
        $cashier = User::factory()->cashier()->create();
        $this->openTill($cashier);
        $order = $this->makeOrder();

        $this->actingAs($cashier)->post("/payments", [
            "order_id" => $order->id,
            "paid_at" => now()->format("Y-m-d"),
            "amount" => 1000,
            "method" => "cash",
        ])->assertRedirect();

        $this->assertDatabaseHas("payments", ["order_id" => $order->id, "amount" => 1000]);
    }

    private function makeOrder(): Order
    {
        $customer = Customer::create(["name" => "Test Customer", "phone" => "+258840000001", "address" => "Test Address"]);
        $service = Service::create(["name" => "Wet Wash", "type" => "by_weight", "price" => 4000, "estimated_days" => 1]);

        $order = Order::create([
            "order_number" => "ORDTEST0001",
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 8000,
            "status" => "received",
        ]);

        OrderItem::create([
            "order_id" => $order->id,
            "service_id" => $service->id,
            "type" => "by_weight",
            "weight" => 2,
            "amount" => 1,
            "unit_price" => 4000,
            "subtotal" => 8000,
        ]);

        return $order;
    }
}
