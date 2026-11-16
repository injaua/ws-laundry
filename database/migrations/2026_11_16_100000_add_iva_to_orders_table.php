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
            $table->decimal("subtotal", 12, 2)->nullable()->after("total_price");
            $table->decimal("iva_rate", 5, 2)->nullable()->after("subtotal");
            $table->decimal("iva_amount", 12, 2)->default(0)->after("iva_rate");
        });

        // Legacy orders had no VAT: base equals the old total.
        DB::table("orders")->whereNull("subtotal")
            ->update(["subtotal" => DB::raw("total_price"), "iva_rate" => 0]);
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->dropColumn(["subtotal", "iva_rate", "iva_amount"]);
        });
    }
};
