<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The seeded categories were left without an account, so their expenses
     * never reached the journal. Only salary was mapped (migration _150000).
     */
    public function up(): void
    {
        $map = [
            "detergent" => "41",
            "electricity" => "41",
            "water" => "41",
            "lainnya" => "61",
        ];

        foreach ($map as $key => $code) {
            $accountId = DB::table("accounts")->where("code", $code)->value("id");
            if (!$accountId) {
                continue;
            }

            DB::table("expense_categories")
                ->where("key", $key)
                ->whereNull("account_id")
                ->update(["account_id" => $accountId, "updated_at" => now()]);
        }
    }

    public function down(): void
    {
        DB::table("expense_categories")
            ->whereIn("key", ["detergent", "electricity", "water", "lainnya"])
            ->update(["account_id" => null, "updated_at" => now()]);
    }
};
