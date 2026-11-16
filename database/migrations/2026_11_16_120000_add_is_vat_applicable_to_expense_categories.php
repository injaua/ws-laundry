<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Normal VAT regime: supplier expense amounts are gross (VAT-inclusive),
     * so deductible input VAT (4.4.3.1) is split out only for flagged categories.
     */
    public function up(): void
    {
        Schema::table("expense_categories", function (Blueprint $table) {
            $table->boolean("is_vat_applicable")->default(false)->after("is_salary");
        });

        DB::table("expense_categories")
            ->whereIn("key", ["detergent", "electricity", "water"])
            ->update(["is_vat_applicable" => true, "updated_at" => now()]);
    }

    public function down(): void
    {
        Schema::table("expense_categories", function (Blueprint $table) {
            $table->dropColumn("is_vat_applicable");
        });
    }
};
