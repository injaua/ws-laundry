<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->boolean("is_credit_customer")->default(false)->after("address");
            $table->decimal("credit_limit", 12, 0)->default(0)->after("is_credit_customer");
            $table->unsignedSmallInteger("credit_terms_days")->default(30)->after("credit_limit");
        });
    }

    public function down(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->dropColumn(["is_credit_customer", "credit_limit", "credit_terms_days"]);
        });
    }
};
