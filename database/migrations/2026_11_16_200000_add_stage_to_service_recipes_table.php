<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("service_recipes", function (Blueprint $table) {
            $table->dropUnique(["service_id", "inventory_item_id"]);
        });

        Schema::table("service_recipes", function (Blueprint $table) {
            $table->string("stage", 10)->default("washing")->after("basis");
            $table->unique(["service_id", "inventory_item_id", "stage"]);
        });
    }

    public function down(): void
    {
        Schema::table("service_recipes", function (Blueprint $table) {
            $table->dropUnique(["service_id", "inventory_item_id", "stage"]);
            $table->dropColumn("stage");
        });

        Schema::table("service_recipes", function (Blueprint $table) {
            $table->unique(["service_id", "inventory_item_id"]);
        });
    }
};
