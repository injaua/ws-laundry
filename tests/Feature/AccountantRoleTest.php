<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accountant: everything about accounting, tax, treasury, receivables, invoicing, expenses,
 * payroll and financial reports; orders, customers, tills and payments are read-only;
 * no counter work, production, logistics, HR files, users or settings.
 */
class AccountantRoleTest extends TestCase
{
    use RefreshDatabase;

    private function accountant(): User
    {
        return User::factory()->accountant()->create(["name" => "Clara Contabilista"]);
    }

    public function test_accountant_reaches_every_accounting_and_finance_page(): void
    {
        $accountant = $this->accountant();

        foreach ([
            "/accounting/accounts", "/accounting/journal-entries", "/accounting/trial-balance", "/accounting/balance-sheet",
            "/accounting/income-statement", "/accounting/vat-settlement", "/accounting/state-payments", "/accounting/period-close",
            "/expenses", "/expenses/create", "/expense-categories",
            "/receivables", "/invoices", "/invoices/create",
            "/treasury/cheques", "/treasury/deposits", "/wallet-reconciliation",
            "/reports", "/reports/profit-loss", "/reports/payment-methods", "/management",
            "/salaries", "/salaries/create", "/salaries/summary",
            "/dashboard", "/messages",
        ] as $url) {
            $this->actingAs($accountant)->get($url)->assertOk();
        }
    }

    public function test_operational_records_are_read_only(): void
    {
        $accountant = $this->accountant();
        $customer = Customer::create(["name" => "Hotel Sol", "phone" => "841234567", "address" => "Matola"]);
        $order = Order::create([
            "order_number" => "ORD-ACC-1", "customer_id" => $customer->id, "status" => "washing",
            "received_at" => now()->toDateString(), "total_price" => 500,
        ]);

        foreach (["/orders", "/orders/{$order->id}", "/orders/{$order->id}/invoice", "/customers", "/customers/{$customer->id}",
            "/credit-notes", "/cash-sessions", "/mobile-payments", "/resources/suppliers", "/resources/purchases"] as $url) {
            $this->actingAs($accountant)->get($url)->assertOk();
        }

        // The order page shows the documents, not the counter's tools.
        $this->actingAs($accountant)->get("/orders/{$order->id}")
            ->assertDontSee(route("orders.payment", $order), false)
            ->assertDontSee('action="' . route("orders.update", $order) . '"', false); // update / delete forms
        $this->actingAs($accountant)->get("/orders")->assertDontSee(route("orders.create"), false);

        // …and the counter's actions are refused.
        $this->actingAs($accountant)->get("/orders/{$order->id}/payment")->assertForbidden();
        $this->actingAs($accountant)->post("/payments", ["order_id" => $order->id, "amount" => 100])->assertForbidden();
        $this->actingAs($accountant)->put("/orders/{$order->id}", ["status" => "ready"])->assertForbidden();
        $this->actingAs($accountant)->post("/customers", ["name" => "X"])->assertForbidden();
        $this->actingAs($accountant)->post("/cash-sessions", ["opening_float" => 0])->assertForbidden();
        $this->actingAs($accountant)->post("/customers/{$customer->id}/prices", [])->assertForbidden();
        $this->assertSame("washing", $order->fresh()->status);
    }

    public function test_accountant_has_no_access_outside_finance(): void
    {
        $accountant = $this->accountant();

        foreach ([
            "/production", "/logistics", "/tracking", "/complaints", "/employees", "/vacations", "/attendance",
            "/users", "/services", "/promotions", "/activity-logs", "/management/targets",
            "/resources/equipments", "/resources/items", "/resources/purchases/create", "/resources/suppliers/create",
            "/admin/system-settings/general", "/admin/notifications", "/admin/security", "/messages/clients",
        ] as $url) {
            $this->actingAs($accountant)->get($url)->assertForbidden();
        }
    }

    public function test_reopening_a_closed_period_stays_with_management(): void
    {
        $period = AccountingPeriod::create(["year" => 2026, "month" => 1, "status" => "closed", "closed_at" => now()]);

        $this->actingAs($this->accountant())->patch("/accounting/period-close/{$period->id}/reopen")->assertForbidden();
        $this->assertTrue($period->fresh()->isClosed());
    }

    public function test_sidebar_shows_finance_and_hides_counter_work(): void
    {
        $html = $this->actingAs($this->accountant())->get("/dashboard")->assertOk()->getContent();
        $sidebar = str($html)->after('id="sidebar"')->before("</nav>");

        foreach (["accounting.trial-balance", "accounting.journal-entries.index", "receivables.index", "invoices.index", "expenses.index",
            "treasury.cheques", "reports", "management.index", "salaries.index", "cash-sessions.index", "orders.index"] as $name) {
            $this->assertStringContainsString(route($name), $sidebar, "missing $name");
        }
        foreach (["production.board", "logistics.index", "tracking.index", "employees.index", "users.index", "cash-sessions.current", "resources.equipments.index"] as $name) {
            $this->assertStringNotContainsString(route($name), $sidebar, "should hide $name");
        }
    }

    public function test_admin_and_owner_can_create_an_accountant(): void
    {
        $data = fn ($email) => ["name" => "Clara", "email" => $email, "password" => "Segura#2026pass", "password_confirmation" => "Segura#2026pass", "role" => "accountant", "is_active" => 1];

        $this->actingAs(User::factory()->create())->get("/users/create")->assertOk()->assertSee(__("app.role_accountant"));
        $this->actingAs(User::factory()->create())->post("/users", $data("clara@example.test"))->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->owner()->create())->post("/users", $data("clara2@example.test"))->assertSessionHasNoErrors();

        $this->assertSame(2, User::where("role", "accountant")->count());
    }
}
