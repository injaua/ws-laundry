<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("inventory_lots", function (Blueprint $table) {
            $table->id();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->string("lot_number", 60);
            $table->decimal("quantity", 8, 2);
            $table->decimal("remaining_qty", 8, 2);
            $table->decimal("unit_cost", 12, 0)->nullable();
            $table->date("expires_at")->nullable();
            $table->date("received_at");
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->unique(["inventory_item_id", "lot_number"]);
            $table->index(["inventory_item_id", "remaining_qty", "expires_at"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("inventory_lots");
    }
};
