<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("inventory_movements", function (Blueprint $table) {
            $table->foreignId("inventory_lot_id")->nullable()->after("inventory_item_id")->constrained("inventory_lots")->nullOnDelete();
            $table->string("source_type", 30)->nullable()->after("user_id");
            $table->unsignedBigInteger("source_id")->nullable()->after("source_type");
            $table->index(["source_type", "source_id"]);
        });
    }

    public function down(): void
    {
        Schema::table("inventory_movements", function (Blueprint $table) {
            $table->dropIndex(["source_type", "source_id"]);
            $table->dropConstrainedForeignId("inventory_lot_id");
            $table->dropColumn(["source_type", "source_id"]);
        });
    }
};
