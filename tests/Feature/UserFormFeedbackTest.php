<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user form always tells what went wrong: every error is listed at the top and next to
 * its field, in the user's language, and the input is kept.
 */
class UserFormFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function valid(array $overrides = []): array
    {
        return array_merge([
            "name" => "Ana Caixa", "email" => "ana@test.local",
            "password" => "Password2026", "password_confirmation" => "Password2026",
            "role" => "cashier",
        ], $overrides);
    }

    public function test_form_renders_the_helpers(): void
    {
        $this->actingAs(User::factory()->create())->get(route("users.create"))->assertOk()
            ->assertSee('id="userForm"', false)
            ->assertSee(__("app.pw_rule_length"))
            ->assertSee('data-toggle-password="userPassword"', false)
            ->assertSee('data-page="user-form"', false);
    }

    public function test_every_password_problem_is_shown_with_readable_field_names(): void
    {
        $admin = User::factory()->create();

        $this->assertDatabaseCount("users", 1);
        $html = $this->actingAs($admin)->from(route("users.create"))->followingRedirects()
            ->post(route("users.store"), $this->valid(["password" => "abc", "password_confirmation" => "abc"]))
            ->assertOk()
            ->assertSee(__("app.user_form_errors"))
            ->assertSee('value="Ana Caixa"', false) // input kept
            ->getContent();

        // length, case and number: each listed at the top and under the field
        $this->assertSame(6, substr_count($html, "O campo palavra-passe deve"));
        $this->assertStringNotContainsString("O campo password", $html);
    }

    public function test_capitalised_email_is_accepted_in_lower_case(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route("users.store"), $this->valid(["email" => "  Ana@Test.Local "]))
            ->assertRedirect(route("users.index"))
            ->assertSessionHas("success", __("app.user_created_named", ["name" => "Ana Caixa"]));

        $this->assertDatabaseHas("users", ["email" => "ana@test.local"]);
    }

    public function test_taken_email_and_missing_role_are_explained(): void
    {
        $admin = User::factory()->create(["email" => "boss@test.local"]);

        $this->actingAs($admin)->from(route("users.create"))->followingRedirects()
            ->post(route("users.store"), $this->valid(["email" => "BOSS@test.local", "role" => ""]))
            ->assertOk()
            ->assertSee(__("app.user_form_errors"))
            ->assertSee("O email já está a ser utilizado.")
            ->assertSee("O campo perfil é obrigatório.");

        $this->assertDatabaseCount("users", 1);
    }

    public function test_admin_can_save_their_own_account(): void
    {
        $admin = User::factory()->create();

        // The role and active fields are locked for one's own account (not sent by the browser).
        $this->actingAs($admin)->patch(route("users.update", $admin), [
            "name" => "Novo Nome", "email" => $admin->email, "is_active" => 1,
        ])->assertRedirect(route("users.index"))->assertSessionHasNoErrors();

        $this->assertSame("Novo Nome", $admin->fresh()->name);
        $this->assertSame("admin", $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }
}
