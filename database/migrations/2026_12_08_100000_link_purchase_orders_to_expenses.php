<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A purchase order can be raised from an expense (the purchase is already paid, the
     * goods are still to arrive): the order points at that expense so receiving it does
     * not record the cost a second time. A line can order a piece of equipment instead of
     * a stock item; the equipment waits in "awaiting_delivery" until it is received.
     */
    public function up(): void
    {
        Schema::table("purchase_orders", function (Blueprint $table) {
            $table->foreignId("expense_id")->nullable()->after("user_id")->constrained("expenses")->nullOnDelete();
        });

        Schema::table("purchase_order_items", function (Blueprint $table) {
            $table->unsignedBigInteger("inventory_item_id")->nullable()->change();
            $table->foreignId("equipment_id")->nullable()->after("inventory_item_id")->constrained("equipments")->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table("purchase_order_items", function (Blueprint $table) {
            $table->dropConstrainedForeignId("equipment_id");
        });

        Schema::table("purchase_orders", function (Blueprint $table) {
            $table->dropConstrainedForeignId("expense_id");
        });
    }
};
