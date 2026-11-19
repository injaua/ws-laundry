<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Topbar user menu: the name (not just an initial), the account pages for the role, and logout. */
class UserMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_name_account_pages_and_logout(): void
    {
        $admin = User::factory()->create(["name" => "Ana Admin", "email" => "ana@example.test"]);

        $this->actingAs($admin)->get("/dashboard")->assertOk()
            ->assertSee('class="fw-semibold text-truncate user-menu-name">Ana Admin</span>', false)
            ->assertSee("ana@example.test")
            ->assertSee(route("profile.edit"), false)
            ->assertSee(route("profile.security"), false)
            ->assertSee(route("activity-logs.index"), false)
            ->assertDontSee(route("hr.payslips"), false)
            ->assertSee('action="' . route("logout") . '"', false);
    }

    public function test_cashier_menu_has_own_payslips_and_attendance_but_no_activity_log(): void
    {
        $cashier = User::factory()->cashier()->create(["name" => "Carlos Caixa"]);

        $response = $this->actingAs($cashier)->get("/dashboard")->assertOk()
            ->assertSee("Carlos Caixa")
            ->assertSee(__("app.role_cashier"));

        $menu = str($response->getContent())->after('class="dropdown-menu dropdown-menu-end shadow-sm user-menu"')->before("</ul>");
        $this->assertStringContainsString(route("hr.payslips"), $menu);
        $this->assertStringContainsString(route("hr.attendance"), $menu);
        $this->assertStringNotContainsString(route("activity-logs.index"), $menu);
    }

    public function test_logout_from_the_menu_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create())->post("/logout")->assertRedirect();

        $this->assertGuest();
    }
}
