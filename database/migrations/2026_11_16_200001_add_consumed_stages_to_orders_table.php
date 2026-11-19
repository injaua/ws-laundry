<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->json("consumed_stages")->nullable()->after("materials_consumed_at");
        });

        // Orders that already consumed did so for every recipe at once (pre per-stage behaviour).
        DB::table("orders")->whereNotNull("materials_consumed_at")->update([
            "consumed_stages" => json_encode([
                "washing", "drying", "ironing", "folding", "ready", "delivering", "completed",
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->dropColumn("consumed_stages");
        });
    }
};
