<?php

use App\Models\ActivityLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security and compliance:
 * - users: two-factor authentication (TOTP), last login, last password change;
 * - activity_logs: IP / browser and a hash chain that makes tampering detectable;
 * - customers: anonymization date (data-protection request).
 *
 * Also scrubs password hashes and remember tokens that the audit trail used to copy
 * from user updates, then seals the existing entries into the hash chain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->text("two_factor_secret")->nullable()->after("password");
            $table->text("two_factor_recovery_codes")->nullable()->after("two_factor_secret");
            $table->timestamp("two_factor_confirmed_at")->nullable()->after("two_factor_recovery_codes");
            $table->timestamp("password_changed_at")->nullable()->after("two_factor_confirmed_at");
            $table->timestamp("last_login_at")->nullable()->after("password_changed_at");
            $table->string("last_login_ip", 45)->nullable()->after("last_login_at");
        });

        Schema::table("activity_logs", function (Blueprint $table) {
            $table->string("ip_address", 45)->nullable()->after("new_values");
            $table->string("user_agent", 255)->nullable()->after("ip_address");
            $table->string("hash", 64)->nullable()->after("user_agent");
        });

        Schema::table("customers", function (Blueprint $table) {
            $table->timestamp("anonymized_at")->nullable();
        });

        // 1. Remove secrets that earlier versions copied into the audit trail.
        DB::table("activity_logs")->orderBy("id")->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $changes = [];
                foreach (["old_values", "new_values"] as $column) {
                    if ($row->{$column} === null) {
                        continue;
                    }
                    $values = json_decode($row->{$column}, true);
                    if (!is_array($values)) {
                        continue;
                    }
                    $clean = ActivityLog::scrub($values, $row->subject_type);
                    if ($clean !== $values) {
                        $changes[$column] = json_encode($clean);
                    }
                }
                if ($changes) {
                    DB::table("activity_logs")->where("id", $row->id)->update($changes);
                }
            }
        });

        // 2. Seal the existing entries.
        ActivityLog::rehashAll();
    }

    public function down(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->dropColumn("anonymized_at");
        });
        Schema::table("activity_logs", function (Blueprint $table) {
            $table->dropColumn(["ip_address", "user_agent", "hash"]);
        });
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn(["two_factor_secret", "two_factor_recovery_codes", "two_factor_confirmed_at", "password_changed_at", "last_login_at", "last_login_ip"]);
        });
    }
};
