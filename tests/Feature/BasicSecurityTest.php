<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BasicSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_order_status_lookup_is_throttled(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->get("/order-status?order_number=ORD000000{$i}")->assertOk();
        }

        $this->get("/order-status?order_number=ORD0000011")->assertStatus(429);
    }

    public function test_seeder_uses_default_password_only_in_local_or_testing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where("email", "admin@ws-cleanlaundry.example")->firstOrFail();
        $this->assertTrue(Hash::check("password", $admin->password));
    }

    public function test_seeder_uses_random_passwords_outside_local(): void
    {
        $this->app["env"] = "production";

        // --force skips the "running in production" confirmation prompt.
        $this->artisan("db:seed", ["--class" => DatabaseSeeder::class, "--force" => true])
            ->assertSuccessful();

        foreach (["admin", "cashier", "owner"] as $role) {
            $user = User::where("email", "{$role}@ws-cleanlaundry.example")->firstOrFail();
            $this->assertFalse(Hash::check("password", $user->password), "{$role} kept the default password");
        }
    }
}
