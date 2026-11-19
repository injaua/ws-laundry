<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Activity entries of administrators on records without a store (their own logins, global
 * settings, other admins, backups, currencies, languages) were filed under the store chosen in
 * the switcher, so that store's owner could see them. They become company-wide (store_id null,
 * visible to admins only). Changes to a store are filed under that store.
 *
 * store_id is not part of the entry's hash, so the audit chain stays intact.
 */
return new class extends Migration
{
    public function up(): void
    {
        $user = "App\\Models\\User";
        $admins = DB::table("users")->where("role", "admin")->pluck("id");
        if ($admins->isEmpty()) {
            return;
        }
        $byAdmin = fn () => DB::table("activity_logs")->where("actor_type", $user)->whereIn("actor_id", $admins);

        $byAdmin()->where("subject_type", "App\\Models\\Store")->update(["store_id" => DB::raw("subject_id")]);

        $storelessUsers = DB::table("users")->whereNull("store_id")->pluck("id")->push(0);
        $byAdmin()->where("subject_type", $user)->whereIn("subject_id", $storelessUsers)->update(["store_id" => null]);

        $globalSettings = DB::table("site_settings")->whereNull("store_id")->pluck("id");
        $byAdmin()->where("subject_type", "App\\Models\\SiteSetting")->whereIn("subject_id", $globalSettings)->update(["store_id" => null]);

        $byAdmin()->whereIn("subject_type", ["App\\Models\\Currency", "App\\Models\\Language"])->update(["store_id" => null]);
    }

    public function down(): void
    {
        // Not reversible: the store chosen in the switcher at the time is not recorded.
    }
};
