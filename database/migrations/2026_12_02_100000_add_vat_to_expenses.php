<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACCOUNT = ["4.4.3.2", "IVA dedutível"];

    /**
     * Each expense records the VAT rate charged on the purchase (its amount stays gross);
     * the VAT part is posted to IVA dedutível (4.4.3.2), created here in every store's chart.
     * Existing expenses keep vat_rate null and fall back to their category's rule.
     */
    public function up(): void
    {
        Schema::table("expenses", function (Blueprint $table) {
            $table->decimal("vat_rate", 5, 2)->nullable()->after("amount");
            $table->decimal("vat_amount", 12, 2)->default(0)->after("vat_rate");
        });

        $parents = DB::table("accounts")->where("code", "4")->where("is_group", true)->get(["id", "store_id"]);
        foreach ($parents as $parent) {
            $exists = DB::table("accounts")
                ->where("store_id", $parent->store_id)
                ->where("code", self::ACCOUNT[0])
                ->exists();
            if (!$exists) {
                DB::table("accounts")->insert([
                    "code" => self::ACCOUNT[0], "name" => self::ACCOUNT[1], "type" => "asset",
                    "is_group" => false, "is_active" => true, "parent_id" => $parent->id,
                    "store_id" => $parent->store_id, "created_at" => now(), "updated_at" => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table("accounts")->where("code", self::ACCOUNT[0])->pluck("id");
        $used = DB::table("journal_lines")->whereIn("account_id", $ids)->pluck("account_id")->unique();
        DB::table("accounts")->whereIn("id", $ids->diff($used))->delete();

        Schema::table("expenses", function (Blueprint $table) {
            $table->dropColumn(["vat_rate", "vat_amount"]);
        });
    }
};
