<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Order;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use App\Support\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Several stores in one company: each store's data never reaches another store.
 */
class MultiStoreTest extends TestCase
{
    use RefreshDatabase;

    private Store $main;

    private Store $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->main = Store::orderBy("id")->first();
        $this->actingAs(User::factory()->create());
        $this->post(route("admin.stores.store"), [
            "name" => "Loja da Matola", "code" => "lj02", "is_active" => 1,
            "copy_from" => $this->main->id, "copy_catalogue" => 1,
        ])->assertRedirect(route("admin.stores.index"));
        $this->second = Store::where("code", "LJ02")->firstOrFail();
        auth()->logout();
        CurrentStore::flush();
    }

    private function ownerOf(Store $store): User
    {
        return User::factory()->owner()->create(["store_id" => $store->id]);
    }

    private function customerIn(Store $store, string $name, string $phone): Customer
    {
        return CurrentStore::run($store, fn () => Customer::create(["name" => $name, "phone" => $phone, "address" => "Maputo"]));
    }

    public function test_new_store_gets_the_reference_data_but_no_business_data(): void
    {
        $this->customerIn($this->main, "Cliente A", "+258841111111");

        CurrentStore::run($this->second, function () {
            $this->assertSame(CurrentStore::run($this->main, fn () => Account::count()), Account::count());
            $this->assertGreaterThan(0, Account::count());
            $this->assertTrue(Account::where("code", "1.1")->exists());
            // categories point to the new store's own accounts
            $category = ExpenseCategory::whereNotNull("account_id")->first();
            $this->assertSame($this->second->id, $category->account->store_id);
            $this->assertSame(0, Customer::count());
        });
    }

    public function test_a_store_never_sees_another_stores_records(): void
    {
        $customerA = $this->customerIn($this->main, "Cliente A", "+258841111111");
        $customerB = $this->customerIn($this->second, "Cliente B", "+258842222222");
        $ownerB = $this->ownerOf($this->second);

        $this->actingAs($ownerB)->get(route("customers.index"))->assertOk()
            ->assertSee("Cliente B")->assertDontSee("Cliente A");
        $this->actingAs($ownerB)->get(route("customers.show", $customerA))->assertNotFound();
        $this->actingAs($ownerB)->get(route("customers.edit", $customerA))->assertNotFound();
        $this->actingAs($ownerB)->get(route("customers.show", $customerB))->assertOk();
    }

    public function test_forms_cannot_point_at_another_stores_records(): void
    {
        $customerA = $this->customerIn($this->main, "Cliente A", "+258841111111");
        $serviceB = CurrentStore::run($this->second, fn () => Service::create(["name" => "Lavar", "type" => "by_piece", "price" => 100, "estimated_days" => 1]));
        $cashierB = User::factory()->cashier()->create(["store_id" => $this->second->id]);

        $this->actingAs($cashierB)->post("/orders", [
            "customer_id" => $customerA->id,
            "received_at" => now()->toDateString(),
            "items" => [["service_id" => $serviceB->id, "amount" => 1]],
        ])->assertSessionHasErrors("customer_id");

        $this->assertSame(0, Order::acrossStores()->count());
    }

    public function test_records_are_stamped_with_the_store_and_cannot_move(): void
    {
        $ownerB = $this->ownerOf($this->second);
        $this->actingAs($ownerB)->post(route("customers.store"), [
            "name" => "Novo", "phone" => "+258843333333", "address" => "Matola",
        ])->assertRedirect();

        $customer = Customer::acrossStores()->where("name", "Novo")->firstOrFail();
        $this->assertSame($this->second->id, $customer->store_id);

        $this->expectException(LogicException::class);
        $customer->store_id = $this->main->id;
        $customer->save();
    }

    public function test_the_same_phone_or_code_may_exist_in_two_stores(): void
    {
        $this->customerIn($this->main, "Cliente A", "+258841111111");
        $this->customerIn($this->second, "Cliente A (Matola)", "+258841111111");

        $this->assertSame(2, Customer::acrossStores()->where("phone", "+258841111111")->count());
    }

    public function test_owner_creates_users_only_in_their_store(): void
    {
        $ownerB = $this->ownerOf($this->second);
        $cashierA = User::factory()->cashier()->create(["store_id" => $this->main->id, "name" => "Caixa Sede"]);

        $this->actingAs($ownerB)->post(route("users.store"), [
            "name" => "Caixa Matola", "email" => "caixa.matola@test.local",
            "password" => "Password2026", "password_confirmation" => "Password2026",
            "role" => "cashier", "store_id" => $this->main->id, // ignored for owners
        ])->assertRedirect(route("users.index"));

        $this->assertSame($this->second->id, User::where("email", "caixa.matola@test.local")->value("store_id"));
        $this->actingAs($ownerB)->get(route("users.index"))->assertOk()
            ->assertSee("Caixa Matola")->assertDontSee("Caixa Sede");
        $this->actingAs($ownerB)->get(route("users.edit", $cashierA))->assertForbidden();
    }

    public function test_admin_creates_an_owner_for_a_chosen_store(): void
    {
        $this->actingAs(User::factory()->create())->post(route("users.store"), [
            "name" => "Dono Matola", "email" => "dono.matola@test.local",
            "password" => "Password2026", "password_confirmation" => "Password2026",
            "role" => "owner", "store_id" => $this->second->id,
        ])->assertRedirect(route("users.index"));

        $this->assertSame($this->second->id, User::where("email", "dono.matola@test.local")->value("store_id"));
    }

    public function test_only_the_admin_manages_stores(): void
    {
        $owner = $this->ownerOf($this->main);

        $this->actingAs($owner)->get(route("admin.stores.index"))->assertForbidden();
        $this->actingAs($owner)->get(route("admin.stores.overview"))->assertForbidden();
        $this->actingAs($owner)->post(route("admin.stores.store"), ["name" => "X", "code" => "X1"])->assertForbidden();
        $this->actingAs($owner)->post(route("admin.stores.switch", $this->second))->assertForbidden();

        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route("admin.stores.index"))->assertOk()->assertSee("Loja da Matola");
        $this->actingAs($admin)->get(route("admin.stores.create"))->assertOk();
        $this->actingAs($admin)->get(route("admin.stores.edit", $this->second))->assertOk();
    }

    public function test_admin_switches_store_and_works_with_its_data(): void
    {
        $this->customerIn($this->main, "Cliente A", "+258841111111");
        $this->customerIn($this->second, "Cliente B", "+258842222222");
        $admin = User::factory()->create();

        $this->actingAs($admin)->get(route("customers.index"))->assertSee("Cliente A")->assertDontSee("Cliente B");

        $this->actingAs($admin)->post(route("admin.stores.switch", $this->second))->assertRedirect();
        $this->actingAs($admin)->get(route("customers.index"))->assertSee("Cliente B")->assertDontSee("Cliente A")
            ->assertSee("Loja da Matola");
    }

    public function test_overview_shows_each_store_and_the_company_total(): void
    {
        $this->customerIn($this->main, "Cliente A", "+258841111111");
        $this->customerIn($this->second, "Cliente B", "+258842222222");
        $this->customerIn($this->second, "Cliente C", "+258843333333");

        $response = $this->actingAs(User::factory()->create())->get(route("admin.stores.overview"))->assertOk()
            ->assertSee($this->main->name)->assertSee("Loja da Matola");

        $rows = $response->viewData("stores");
        $this->assertSame(1, $rows->firstWhere("store.id", $this->main->id)["customers"]);
        $this->assertSame(2, $rows->firstWhere("store.id", $this->second->id)["customers"]);
        $this->assertSame(3, $response->viewData("totals")["customers"]);
    }

    public function test_settings_are_per_store_with_company_fallback(): void
    {
        CurrentStore::run($this->main, fn () => SiteSetting::set("company_name", "Lavandaria Sede"));
        CurrentStore::run($this->second, fn () => SiteSetting::set("company_name", "Lavandaria Matola"));
        // localization is company-wide: set from any store, seen by all
        CurrentStore::run($this->second, fn () => SiteSetting::set("timezone", "Africa/Johannesburg"));
        SystemSettings::flush();

        $this->assertSame("Lavandaria Sede", CurrentStore::run($this->main, fn () => SystemSettings::get("company_name")));
        $this->assertSame("Lavandaria Matola", CurrentStore::run($this->second, fn () => SystemSettings::get("company_name")));
        $this->assertSame("Africa/Johannesburg", CurrentStore::run($this->main, fn () => SystemSettings::get("timezone")));
        $this->assertNull(SiteSetting::where("key", "timezone")->whereNotNull("store_id")->first());
    }

    public function test_activity_log_is_per_store_for_owners(): void
    {
        $this->customerIn($this->main, "Cliente A", "+258841111111");
        $this->customerIn($this->second, "Cliente B", "+258842222222");

        $this->assertSame(1, ActivityLog::where("store_id", $this->second->id)->where("subject_type", Customer::class)->count());

        $ownerB = $this->ownerOf($this->second);
        $logs = $this->actingAs($ownerB)->get(route("activity-logs.index"))->assertOk()->viewData("logs");
        $this->assertTrue(collect($logs->items())->every(fn ($log) => $log->store_id === $this->second->id));
    }

    public function test_public_order_status_finds_the_order_in_its_store(): void
    {
        $customer = $this->customerIn($this->second, "Cliente B", "+258842222222");
        $order = CurrentStore::run($this->second, fn () => Order::create([
            "order_number" => "ORD999", "customer_id" => $customer->id, "received_at" => now(),
            "delivery_date" => now()->addDay(), "status" => "received", "total_price" => 100,
        ]));

        $this->get(route("orders.check-status", ["order_number" => "ORD999", "phone_last4" => "2222"]))
            ->assertOk()->assertSee("ORD999")->assertSee("Cliente B");
        $this->assertSame($this->second->id, $order->store_id);
    }
}
