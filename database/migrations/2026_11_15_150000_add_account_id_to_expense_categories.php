<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("expense_categories", function (Blueprint $table) {
            $table->foreignId("account_id")->nullable()->constrained("accounts")->nullOnDelete();
        });

        $payrollAccount = DB::table("accounts")->where("code", "42")->value("id");
        if ($payrollAccount) {
            DB::table("expense_categories")->where("is_salary", true)
                ->update(["account_id" => $payrollAccount, "updated_at" => now()]);
        }
    }

    public function down(): void
    {
        Schema::table("expense_categories", function (Blueprint $table) {
            $table->dropForeign(["account_id"]);
            $table->dropColumn("account_id");
        });
    }
};
