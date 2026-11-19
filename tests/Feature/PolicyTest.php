<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_management_is_limited_to_admin_and_owner(): void
    {
        $order = $this->order();

        foreach (["admin" => true, "owner" => true, "cashier" => false] as $role => $allowed) {
            $user = User::factory()->create(["role" => $role]);

            $this->assertTrue($user->can("view", $order), "$role view");
            $this->assertTrue($user->can("create", Order::class), "$role create");
            $this->assertTrue($user->can("update", $order), "$role update");
            $this->assertSame($allowed, $user->can("edit", $order), "$role edit");
            $this->assertSame($allowed, $user->can("delete", $order), "$role delete");
            $this->assertSame($allowed, $user->can("restore", $order), "$role restore");
        }
    }

    public function test_owner_manages_only_the_staff_of_their_store(): void
    {
        $owner = User::factory()->owner()->create();
        $ownCashier = User::factory()->cashier()->create(["created_by" => $owner->id]);
        $otherCashier = User::factory()->cashier()->create(["store_id" => Store::create(["name" => "Outra loja", "code" => "LJ02"])->id]);
        $admin = User::factory()->create();

        $this->assertTrue($owner->can("update", $ownCashier));
        $this->assertFalse($owner->can("update", $otherCashier));
        $this->assertFalse($owner->can("update", $admin));
        $this->assertFalse($owner->can("toggleActive", $ownCashier));

        $this->assertTrue($admin->can("update", $otherCashier));
        $this->assertTrue($admin->can("toggleActive", $otherCashier));
    }

    public function test_owner_cannot_assign_roles_above_cashier(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post("/users", [
            "name" => "Sneaky",
            "email" => "sneaky@example.com",
            "password" => "Str0ng-Passw0rd!",
            "password_confirmation" => "Str0ng-Passw0rd!",
            "role" => "admin",
        ])->assertSessionHasErrors("role");

        $this->assertDatabaseMissing("users", ["email" => "sneaky@example.com"]);
    }

    public function test_owner_cannot_edit_another_owners_cashier_over_http(): void
    {
        $owner = User::factory()->owner()->create();
        $foreign = User::factory()->cashier()->create(["store_id" => Store::create(["name" => "Outra loja", "code" => "LJ02"])->id]);

        $this->actingAs($owner)->put("/users/{$foreign->id}", [
            "name" => "Changed",
            "email" => $foreign->email,
            "role" => "cashier",
            "is_active" => 1,
        ])->assertForbidden();

        $this->assertNotSame("Changed", $foreign->fresh()->name);
    }

    private function order(): Order
    {
        $customer = Customer::create(["name" => "Policy", "phone" => "+258840000020", "address" => "Matola"]);

        return Order::create([
            "order_number" => "ORDPOLICY1",
            "customer_id" => $customer->id,
            "received_at" => now(),
            "total_price" => 1000,
            "status" => "received",
        ]);
    }
}
