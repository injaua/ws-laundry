<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Card payments on a POS terminal: in every store's chart, the money waits in its own
 * account until the bank settles the batch, apart from transfers (1.2.1) and the
 * mobile wallets (1.2.2–1.2.4).
 */
return new class extends Migration
{
    private const ACCOUNT = ["1.2.5", "Pagamentos POS (cartões)"];

    public function up(): void
    {
        $parents = DB::table("accounts")->where("code", "1.2")->get(["id", "store_id"]);
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
    }
};
