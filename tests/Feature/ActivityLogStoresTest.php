<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\Store;
use App\Models\User;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Activity log: the admin sees what happens in every store (and company-wide); an owner sees
 * only their own store — never the admin's logins, global settings or another store.
 */
class ActivityLogStoresTest extends TestCase
{
    use RefreshDatabase;

    private Store $main;

    private Store $second;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->main = Store::orderBy("id")->first();
        $this->second = Store::create(["name" => "Loja da Matola", "code" => "LJ02", "is_active" => true]);
        $this->admin = User::factory()->create();
        CurrentStore::flush();
    }

    private function ownerOf(Store $store): User
    {
        return User::factory()->create(["role" => "owner", "store_id" => $store->id]);
    }

    private function customerIn(Store $store, string $name): Customer
    {
        return CurrentStore::run($store, fn () => Customer::create(["name" => $name, "phone" => "+25884" . random_int(1000000, 9999999)]));
    }

    /** Logs shown to $user (all pages). */
    private function visibleTo(User $user, array $query = []): Collection
    {
        return collect($this->actingAs($user)->get(route("activity-logs.index", $query))
            ->assertOk()->viewData("logs")->items());
    }

    public function test_admin_actions_without_a_store_are_company_wide(): void
    {
        // The admin works in the second store (switcher) and does store-less things.
        $this->actingAs($this->admin)->post(route("admin.stores.switch", $this->second))->assertRedirect();
        ActivityLog::security("login", $this->admin, "Login", $this->admin);
        ActivityLog::security("backup_created", null, "Backup created", $this->admin);
        ActivityLog::log($this->admin, "updated", SiteSetting::create(["key" => "company_name", "value" => "X"]), "Setting");

        $this->assertSame(0, ActivityLog::where("store_id", $this->second->id)->whereIn("action", ["login", "backup_created"])->count());
        $this->assertSame(3, ActivityLog::whereNull("store_id")->whereIn("description", ["Login", "Backup created", "Setting"])->count());

        // ...but what they do inside a store belongs to that store.
        $this->actingAs($this->admin);
        $customer = $this->customerIn($this->second, "Cliente B");
        $this->assertSame($this->second->id, ActivityLog::where("subject_type", Customer::class)->where("subject_id", $customer->id)->value("store_id"));
    }

    public function test_owner_sees_only_their_store(): void
    {
        $ownerA = $this->ownerOf($this->main);
        $ownerB = $this->ownerOf($this->second);
        $this->customerIn($this->main, "Cliente A");
        $this->customerIn($this->second, "Cliente B");
        ActivityLog::security("login", $this->admin, "Admin login", $this->admin);
        ActivityLog::security("login", $ownerA, "Owner A login", $ownerA);

        $seenByB = $this->visibleTo($ownerB);
        $this->assertNotEmpty($seenByB);
        $this->assertTrue($seenByB->every(fn ($log) => $log->store_id === $this->second->id));
        $this->assertFalse($seenByB->contains("description", "Admin login"));
        $this->assertFalse($seenByB->contains("description", "Owner A login"));

        // The store filter is ignored for an owner.
        $this->assertTrue($this->visibleTo($ownerB, ["store" => $this->main->id])->every(fn ($log) => $log->store_id === $this->second->id));
        $this->assertTrue($this->visibleTo($ownerB, ["store" => "global"])->every(fn ($log) => $log->store_id === $this->second->id));

        $this->actingAs($ownerB)->get(route("activity-logs.index"))
            ->assertDontSee(__("app.log_company_wide"))
            ->assertDontSee("Loja da Matola</option>", false);
    }

    public function test_admin_sees_every_store_and_can_filter(): void
    {
        $this->customerIn($this->main, "Cliente A");
        $this->customerIn($this->second, "Cliente B");
        ActivityLog::security("login", $this->admin, "Admin login", $this->admin);

        $all = $this->visibleTo($this->admin);
        $this->assertTrue($all->contains("store_id", $this->main->id));
        $this->assertTrue($all->contains("store_id", $this->second->id));
        $this->assertTrue($all->contains("description", "Admin login"));

        $this->assertTrue($this->visibleTo($this->admin, ["store" => $this->second->id])->every(fn ($log) => $log->store_id === $this->second->id));
        $global = $this->visibleTo($this->admin, ["store" => "global"]);
        $this->assertTrue($global->contains("description", "Admin login"));
        $this->assertTrue($global->every(fn ($log) => $log->store_id === null));

        $this->actingAs($this->admin)->get(route("activity-logs.index"))
            ->assertSee(__("app.log_company_wide"))
            ->assertSee("Loja da Matola");
    }
}
