<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Abilities (App\Support\Permissions) guard the routes; roles are never listed in the routes. */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_routes_use_known_abilities_and_no_role_lists(): void
    {
        $guarded = 0;
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (!is_string($middleware)) {
                    continue;
                }
                $this->assertStringStartsNotWith("role:", $middleware, "{$route->uri()} lists roles: use an ability");
                if (str_starts_with($middleware, "can:")) {
                    $this->assertArrayHasKey(substr($middleware, 4), Permissions::ABILITIES, "{$route->uri()}: unknown ability");
                    $guarded++;
                }
            }
        }
        $this->assertGreaterThan(300, $guarded);
    }

    public function test_abilities_follow_the_roles(): void
    {
        $owner = User::factory()->owner()->create();
        $cashier = User::factory()->cashier()->create();
        $admin = User::factory()->create();

        $this->assertTrue($owner->can("manage-catalog"));
        $this->assertTrue($owner->can("configure-store"));
        $this->assertFalse($owner->can("administer-company"));
        $this->assertTrue($admin->can("administer-company"));
        $this->assertFalse($cashier->can("manage-catalog"));
        $this->assertTrue($cashier->can("work-counter"));
        $this->assertTrue($cashier->can("answer-customers"));
        $this->assertFalse($admin->can("answer-customers"));

        foreach (Permissions::ABILITIES as $ability => $roles) {
            $this->assertEmpty(array_diff($roles, Permissions::ROLES), "{$ability} names an unknown role");
        }
    }
}
