<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Settings are read once per request and re-read after any change. */
class SettingsCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_are_read_once_and_refreshed_after_a_write(): void
    {
        $store = Store::orderBy("id")->first();

        CurrentStore::run($store, function () use ($store) {
            SiteSetting::set("vat_rate", "16");
            SiteSetting::setAt(null, "express_lead_days", "2");

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->assertSame("16", SiteSetting::get("vat_rate"));
            $this->assertSame("2", SiteSetting::get("express_lead_days"));
            SiteSetting::many(["vat_rate", "qc_required"]);
            SiteSetting::valuesAt($store->id, ["vat_rate"]);
            $this->assertLessThanOrEqual(2, count(DB::getQueryLog())); // company level + store level
            DB::disableQueryLog();

            // A write is seen right away, at the store and at company level.
            SiteSetting::set("vat_rate", "17");
            $this->assertSame("17", SiteSetting::get("vat_rate"));
            SiteSetting::setAt(null, "express_lead_days", "3");
            $this->assertSame("3", SiteSetting::get("express_lead_days"));
            SiteSetting::forgetAt($store->id, ["vat_rate"]);
            $this->assertNotSame("17", SiteSetting::get("vat_rate"));
        });
    }
}
