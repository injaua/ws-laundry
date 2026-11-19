<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\SecuritySettings;
use App\Support\HelpCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Help: every role reaches the manual, but only the topics about what it can do. */
class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::factory()->create(["role" => $role]);
    }

    public function test_every_topic_and_role_summary_has_a_portuguese_page(): void
    {
        foreach (HelpCenter::SECTIONS as $topics) {
            foreach (array_keys($topics) as $key) {
                $this->assertFileExists(HelpCenter::path($key, "pt"));
                $this->assertNotSame("app.help_t_" . str_replace("-", "_", $key), __("app.help_t_" . str_replace("-", "_", $key)));
            }
        }
        foreach (SecuritySettings::ROLES as $role) {
            $this->assertFileExists(HelpCenter::path("role-$role", "pt"));
        }
    }

    public function test_every_role_has_help_right_after_the_activity_log(): void
    {
        foreach (SecuritySettings::ROLES as $role) {
            $user = $this->user($role);
            $page = $user->isCourier() ? "/my-route" : "/dashboard";
            $html = $this->actingAs($user)->get($page)->assertOk()->getContent();
            $account = (string) str($html)->after('id="sidebar"')->before("</nav>")->afterLast(__("app.account"));

            $this->assertStringContainsString(route("help.index"), $account, "$role: no help link");
            if ($user->hasRole(["admin", "owner"])) {
                $this->assertLessThan(strpos($account, route("help.index")), strpos($account, route("activity-logs.index")), "$role: help must follow the activity log");
            }
            $this->actingAs($user)->get("/help")->assertOk()->assertSee(__("app.role_" . $role));
        }
    }

    public function test_topics_are_limited_to_the_role(): void
    {
        $expect = [
            "courier" => ["allowed" => ["getting-started", "courier-route", "account"], "denied" => ["orders", "accounting", "payroll", "settings", "internal-chat"]],
            "cashier" => ["allowed" => ["orders", "payments-till", "whatsapp-inbox", "production", "my-hr"], "denied" => ["accounting", "payroll", "users", "settings", "security", "records-readonly"]],
            "accountant" => ["allowed" => ["accounting", "payroll", "treasury", "records-readonly", "reports"], "denied" => ["orders", "payments-till", "production", "hr", "users", "settings", "whatsapp-inbox"]],
            "owner" => ["allowed" => ["orders", "accounting", "hr", "resources", "users", "settings", "catalog"], "denied" => ["security", "records-readonly", "whatsapp-inbox", "my-hr"]],
            "admin" => ["allowed" => ["security", "settings", "catalog", "accounting", "orders"], "denied" => ["records-readonly", "whatsapp-inbox"]],
        ];

        foreach ($expect as $role => $topics) {
            $user = $this->user($role);
            $index = $this->actingAs($user)->get("/help")->assertOk();
            foreach ($topics["allowed"] as $topic) {
                $index->assertSee(route("help.show", $topic), false);
                $this->actingAs($user)->get("/help/$topic")->assertOk();
            }
            foreach ($topics["denied"] as $topic) {
                $index->assertDontSee(route("help.show", $topic), false);
                $this->actingAs($user)->get("/help/$topic")->assertNotFound();
            }
        }
    }

    public function test_shared_topics_hide_the_actions_of_other_roles(): void
    {
        // Reopening a closed period: the accountant is told to ask, management is told how.
        $this->actingAs($this->user("accountant"))->get("/help/accounting")
            ->assertSee("exclusivo do administrador ou do proprietário")
            ->assertDontSee("Use-o só para corrigir erros");
        $this->actingAs($this->user("owner"))->get("/help/accounting")
            ->assertSee("Use-o só para corrigir erros")
            ->assertDontSee("exclusivo do administrador ou do proprietário");

        // Orders: only management reads about editing and deleting.
        $this->actingAs($this->user("cashier"))->get("/help/orders")
            ->assertSee("só pode <strong>mudar o estado</strong>", false)->assertDontSee("Reciclagem");
        $this->actingAs($this->user("owner"))->get("/help/orders")->assertSee("Reciclagem");

        // Credit notes are issued by management.
        $this->actingAs($this->user("cashier"))->get("/help/after-sales")->assertDontSee("Pode <strong>anular</strong>", false);

        // Markers never leak into the page.
        $this->actingAs($this->user("cashier"))->get("/help/customers")->assertDontSee("roles:")->assertDontSee("Preços negociados");
    }

    public function test_unknown_or_malformed_topics_are_not_found(): void
    {
        $user = $this->user("admin");
        $this->actingAs($user)->get("/help/does-not-exist")->assertNotFound();
        $this->actingAs($user)->get("/help/role-admin")->assertNotFound();
        $this->actingAs($user)->get("/help/..%2F..%2F.env")->assertNotFound();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get("/help")->assertRedirect("/login");
    }
}
