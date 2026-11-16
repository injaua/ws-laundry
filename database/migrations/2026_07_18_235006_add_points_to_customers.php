<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->integer("points")->default(0)->after("address");
            $table->integer("total_transactions")->default(0)->after("points");
        });
    }

    public function down(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->dropColumn(["points", "total_transactions"]);
        });
    }
};
