<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each store keeps its own delivery zones: the same name may exist in two stores
 * (copied when a store is created), but only once within a store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("delivery_zones", function (Blueprint $table) {
            $table->unique(["store_id", "name"]);
        });
    }

    public function down(): void
    {
        Schema::table("delivery_zones", function (Blueprint $table) {
            $table->dropUnique(["store_id", "name"]);
        });
    }
};
