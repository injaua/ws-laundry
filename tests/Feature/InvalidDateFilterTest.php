<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A malformed date in the query string falls back to the default period instead of a 500. */
class InvalidDateFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_with_date_filters_ignore_invalid_dates(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::create(["name" => "Datas", "phone" => "+258840000099", "address" => "Maputo", "is_credit_customer" => true]);

        $pages = [
            "/reports", "/reports/export", "/reports/profit-loss", "/reports/payment-methods",
            "/accounting/trial-balance", "/accounting/balance-sheet", "/accounting/income-statement",
            "/credit-notes", "/mobile-payments", "/production/report", "/management",
            "/customers/{$customer->id}/statement", "/invoices/create?customer_id={$customer->id}",
            "/cash-sessions", "/justifications/create",
        ];
        $bad = ["from" => "lixo", "to" => "2026-99-99", "date" => "abc", "period_start" => "x", "period_end" => "31/31/2026"];

        foreach ($pages as $page) {
            $url = $page . (str_contains($page, "?") ? "&" : "?") . http_build_query($bad);
            $status = $this->actingAs($admin)->get($url)->getStatusCode();
            $this->assertLessThan(500, $status, "$url returned $status");

            $status = $this->actingAs($admin)->get($page . (str_contains($page, "?") ? "&" : "?") . "from[]=1&to[]=2")->getStatusCode();
            $this->assertLessThan(500, $status, "$page with array dates returned $status");
        }
    }

    public function test_list_filters_sent_as_arrays_do_not_crash(): void
    {
        $admin = User::factory()->create();
        $query = "?search[]=a&status[]=a&category[]=a&type[]=a&kind[]=a&source[]=a&customer_id[]=1&date[]=1&page[]=2";

        foreach ([
            "/orders", "/customers", "/expenses", "/services", "/packages", "/users", "/employees",
            "/complaints", "/credit-notes", "/production", "/receivables", "/logistics", "/logistics/create",
            "/logistics/route-sheet", "/messages/clients", "/accounting/journal-entries",
            "/resources/items", "/resources/items/create", "/resources/equipments", "/resources/suppliers", "/resources/purchases",
        ] as $page) {
            $status = $this->actingAs($admin)->get($page . $query)->getStatusCode();
            $this->assertLessThan(500, $status, "$page returned $status");
        }
    }

    public function test_array_filter_keeps_its_first_value(): void
    {
        $admin = User::factory()->create();
        Customer::create(["name" => "Zé Filtro", "phone" => "+258840000098", "address" => "Beira"]);

        $this->actingAs($admin)->get("/customers?search[]=Filtro&search[]=outro")
            ->assertOk()
            ->assertSee("Zé Filtro");
    }

    public function test_request_date_helper(): void
    {
        $default = now()->startOfDay();

        $this->get("/?d=2026-11-05");
        $this->assertSame("2026-11-05", request_date("d", $default)->toDateString());

        foreach (["lixo", "2026-99-99", "", "0001-01-01"] as $value) {
            request()->merge(["d" => $value]);
            $this->assertSame($default, request_date("d", $default), "value '$value'");
        }
        request()->merge(["d" => ["x"]]);
        $this->assertNull(request_date("d"));
    }
}
