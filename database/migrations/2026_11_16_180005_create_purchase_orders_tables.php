<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("purchase_orders", function (Blueprint $table) {
            $table->id();
            $table->string("po_number", 20)->unique();
            $table->foreignId("supplier_id")->constrained("suppliers")->restrictOnDelete();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->date("order_date");
            $table->date("expected_date")->nullable();
            $table->string("status", 12)->default("draft");
            $table->decimal("total", 12, 0)->default(0);
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index(["status", "order_date"]);
        });

        Schema::create("purchase_order_items", function (Blueprint $table) {
            $table->id();
            $table->foreignId("purchase_order_id")->constrained()->cascadeOnDelete();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->decimal("quantity", 8, 2);
            $table->decimal("unit_cost", 12, 0);
            $table->decimal("subtotal", 12, 0);
            $table->decimal("received_qty", 8, 2)->default(0);
            $table->timestamps();
            $table->unique(["purchase_order_id", "inventory_item_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("purchase_order_items");
        Schema::dropIfExists("purchase_orders");
    }
};
