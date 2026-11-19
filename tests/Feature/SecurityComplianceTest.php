<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Security\CustomerPrivacy;
use App\Services\Security\SecuritySettings;
use App\Services\Security\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function userWith2fa(string $role = "admin"): array
    {
        $tf = app(TwoFactor::class);
        $secret = $tf->generateSecret();
        $codes = $tf->recoveryCodes();
        $user = User::factory()->create(["role" => $role]);
        $user->forceFill([
            "two_factor_secret" => $secret,
            "two_factor_recovery_codes" => $codes["hashed"],
            "two_factor_confirmed_at" => now(),
        ])->saveQuietly();

        return [$user->fresh(), $secret, $codes["plain"]];
    }

    public function test_login_without_two_factor_logs_in_and_is_audited(): void
    {
        $user = User::factory()->create();

        $this->post("/login", ["email" => $user->email, "password" => "password"])->assertRedirect("/dashboard");

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(ActivityLog::where("action", "login")->where("subject_id", $user->id)->exists());
    }

    public function test_failed_login_is_audited_without_password(): void
    {
        $user = User::factory()->create();

        $this->post("/login", ["email" => $user->email, "password" => "wrong-pass"]);

        $this->assertGuest();
        $log = ActivityLog::where("action", "login_failed")->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString("wrong-pass", json_encode($log->toArray()));
    }

    public function test_login_with_two_factor_requires_code(): void
    {
        [$user, $secret] = $this->userWith2fa();

        $this->post("/login", ["email" => $user->email, "password" => "password"])->assertRedirect(route("two-factor.challenge"));
        $this->assertGuest();

        $this->post(route("two-factor.verify"), ["code" => "000000"])->assertSessionHasErrors("code");
        $this->assertGuest();
        $this->assertTrue(ActivityLog::where("action", "two_factor_failed")->exists());

        $code = app(TwoFactor::class)->code($secret, time());
        $this->post(route("two-factor.verify"), ["code" => $code])->assertRedirect("/dashboard");
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_works_once(): void
    {
        [$user, , $plain] = $this->userWith2fa();

        $this->post("/login", ["email" => $user->email, "password" => "password"]);
        $this->post(route("two-factor.verify"), ["recovery_code" => $plain[0]])->assertRedirect("/dashboard");
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        auth()->logout();
        $this->post("/login", ["email" => $user->email, "password" => "password"]);
        $this->post(route("two-factor.verify"), ["recovery_code" => $plain[0]])->assertSessionHasErrors("code");
        $this->assertGuest();
    }

    public function test_challenge_without_pending_login_redirects(): void
    {
        $this->get(route("two-factor.challenge"))->assertRedirect(route("login"));
    }

    public function test_enable_two_factor_from_profile(): void
    {
        $user = User::factory()->create(["role" => "cashier"]);

        $this->actingAs($user)->post(route("two-factor.enable"), ["password" => "password"])->assertRedirect();
        $secret = session("two_factor.pending_secret");
        $this->assertNotEmpty($secret);
        $this->get(route("profile.security"))->assertOk()->assertSee("<svg", false);

        $this->post(route("two-factor.confirm"), ["code" => "123456"])->assertSessionHasErrors("code");
        $this->post(route("two-factor.confirm"), ["code" => app(TwoFactor::class)->code($secret, time())])
            ->assertSessionHas("two_factor.recovery_codes");

        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        // Stored encrypted, not in clear.
        $this->assertNotSame($secret, DB::table("users")->where("id", $user->id)->value("two_factor_secret"));
    }

    public function test_required_role_is_forced_to_set_up_two_factor(): void
    {
        SecuritySettings::save(["two_factor_roles" => ["owner"], "idle_minutes" => 60, "backup_retention_days" => 14, "fiscal_retention_years" => 10]);
        $owner = User::factory()->create(["role" => "owner"]);
        $cashier = User::factory()->create(["role" => "cashier"]);

        $this->actingAs($owner)->get("/dashboard")->assertRedirect(route("profile.security"));
        $this->actingAs($owner)->get(route("profile.security"))->assertOk();
        $this->actingAs($cashier)->get("/dashboard")->assertOk();
    }

    public function test_idle_session_is_logged_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(["security.last_activity" => now()->subMinutes(61)->timestamp])
            ->get("/dashboard")->assertRedirect(route("login"));
        $this->assertGuest();
        $this->assertTrue(ActivityLog::where("action", "session_expired")->exists());
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get("/login")
            ->assertHeader("X-Frame-Options", "SAMEORIGIN")
            ->assertHeader("X-Content-Type-Options", "nosniff")
            ->assertHeader("Referrer-Policy", "strict-origin-when-cross-origin");
    }

    public function test_password_policy_for_new_passwords(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put("/password", ["current_password" => "password", "password" => "simples12", "password_confirmation" => "simples12"])
            ->assertSessionHasErrorsIn("updatePassword", "password");

        $this->actingAs($user)->put("/password", ["current_password" => "password", "password" => "Lavandaria2026", "password_confirmation" => "Lavandaria2026"])
            ->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check("Lavandaria2026", $user->password));
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(ActivityLog::where("action", "password_changed")->exists());
    }

    public function test_audit_never_stores_password_hashes(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create(["role" => "cashier"]);

        $this->actingAs($admin);
        $other->update(["password" => Hash::make("Outra2026Senha"), "name" => "Novo Nome"]);

        foreach (ActivityLog::where("subject_type", User::class)->get() as $log) {
            $raw = json_encode([$log->old_values, $log->new_values]);
            $this->assertStringNotContainsString('$2y$', $raw);
            $this->assertStringNotContainsString("remember_token", $raw);
        }
    }

    public function test_audit_trail_is_append_only_and_tamper_evident(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin);
        Customer::create(["name" => "A", "phone" => "+258840000001"]);
        Customer::create(["name" => "B", "phone" => "+258840000002"]);

        $this->assertTrue(ActivityLog::verifyChain()["ok"]);

        $log = ActivityLog::first();
        $this->expectException(\LogicException::class);
        try {
            $log->update(["description" => "x"]);
        } finally {
            // Direct database tampering is detected by the chain.
            DB::table("activity_logs")->where("id", $log->id)->update(["description" => "tampered"]);
            $result = ActivityLog::verifyChain();
            $this->assertFalse($result["ok"]);
            $this->assertSame($log->id, $result["broken_id"]);
        }
    }

    public function test_secret_settings_are_masked_in_audit(): void
    {
        $this->actingAs(User::factory()->create());
        SiteSetting::set("mpesa_api_key", "SUPER-SECRET-KEY");

        $this->assertStringNotContainsString("SUPER-SECRET-KEY", json_encode(ActivityLog::all()->toArray()));
    }

    public function test_user_with_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create();
        $cashier = User::factory()->create(["role" => "cashier"]);
        ActivityLog::log($cashier, "created", null, "something");

        $this->actingAs($admin)->delete(route("users.destroy", $cashier))->assertSessionHas("error");
        $this->assertNotNull($cashier->fresh());
    }

    public function test_profile_self_delete_is_disabled(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete("/profile", ["password" => "password"])->assertRedirect(route("profile.edit"));
        $this->assertNotNull($user->fresh());
    }

    public function test_customer_export_and_anonymize(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "Maria Silva", "phone" => "+258841112223", "email" => "maria@example.com", "address" => "Rua 1"]);

        $this->actingAs($admin)->get(route("customers.privacy.export", $customer))
            ->assertOk()->assertJsonPath("customer.name", "Maria Silva")->assertHeader("Content-Disposition");
        $this->assertTrue(ActivityLog::where("action", "customer_exported")->exists());

        $this->post(route("customers.privacy.anonymize", $customer), ["password" => "password", "confirm" => "1"])->assertRedirect();
        $customer->refresh();
        $this->assertNotNull($customer->anonymized_at);
        $this->assertSame("ANON-{$customer->id}", $customer->phone);
        $this->assertNull($customer->email);
        $this->assertNull($customer->address);
        $this->assertTrue((bool) $customer->whatsapp_opt_out);
        $this->assertStringNotContainsString("maria@example.com", json_encode(ActivityLog::where("action", "customer_anonymized")->get()->toArray()));
    }

    public function test_anonymize_blocked_by_fiscal_documents(): void
    {
        $customer = Customer::create(["name" => "Empresa X", "phone" => "+258841112224", "nuit" => "400000001", "type" => "business"]);
        $this->assertEmpty(app(CustomerPrivacy::class)->blockers($customer));

        DB::table("invoices")->insert(array_filter([
            "customer_id" => $customer->id,
            "number" => "FT-TEST-1",
            "created_at" => now(), "updated_at" => now(),
        ] + $this->invoiceDefaults()));

        $this->assertNotEmpty(app(CustomerPrivacy::class)->blockers($customer->fresh()));
    }

    /** Required non-null invoice columns, filled generically. */
    private function invoiceDefaults(): array
    {
        $defaults = [];
        foreach (DB::select("PRAGMA table_info(invoices)") as $col) {
            if ($col->notnull && $col->dflt_value === null && !$col->pk && !in_array($col->name, ["customer_id", "number", "created_at", "updated_at"], true)) {
                $defaults[$col->name] = match (true) {
                    str_contains($col->type, "date") => now()->toDateString(),
                    str_contains(strtolower($col->type), "int"), str_contains(strtolower($col->type), "numeric"), str_contains(strtolower($col->type), "real") => 0,
                    default => "x",
                };
            }
        }

        return $defaults;
    }

    public function test_admin_security_page_and_backup_access(): void
    {
        $admin = User::factory()->create();
        $owner = User::factory()->create(["role" => "owner"]);

        $this->actingAs($admin)->get(route("admin.security.index"))->assertOk()->assertSee(__("app.check_debug_off"));
        $this->actingAs($owner)->get(route("admin.security.index"))->assertForbidden();

        // Only our own backup file names can be downloaded.
        $this->actingAs($admin)->withSession(["auth.password_confirmed_at" => time()])
            ->get(route("admin.security.backups.download", "backup-evil.txt"))->assertNotFound();

        $this->actingAs($admin)->put(route("admin.security.settings"), [
            "two_factor_roles" => ["admin", "owner"], "idle_minutes" => 30, "backup_retention_days" => 7, "fiscal_retention_years" => 10,
        ])->assertSessionHas("success");
        $this->assertSame(["admin", "owner"], SecuritySettings::twoFactorRoles());
        $this->assertSame(30, SecuritySettings::idleMinutes());

    }

    public function test_activity_log_security_filter(): void
    {
        $admin = User::factory()->create();
        $this->post("/login", ["email" => $admin->email, "password" => "password"]);

        $this->get(route("activity-logs.index", ["action" => "security"]))->assertOk()
            ->assertSee(__("app.log_action_login"))->assertSee(__("app.audit_intact"));
    }
}
