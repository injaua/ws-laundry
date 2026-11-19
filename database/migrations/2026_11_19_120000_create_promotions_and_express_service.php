<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-limited promotions on services (per kg / per piece) and packages, and the
 * express (urgent) surcharge on orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("promotions", function (Blueprint $table) {
            $table->id();
            $table->string("name", 100);
            $table->text("description")->nullable();
            $table->string("discount_type", 10);            // percent | fixed
            $table->decimal("value", 12, 2);
            $table->string("applies_to", 10)->default("all"); // all | selected
            $table->date("starts_on");
            $table->date("ends_on")->nullable();
            $table->json("weekdays")->nullable();            // null = every day; 0 (Sun) … 6 (Sat)
            $table->boolean("is_active")->default(true);
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();
        });

        Schema::create("promotion_items", function (Blueprint $table) {
            $table->id();
            $table->foreignId("promotion_id")->constrained()->cascadeOnDelete();
            $table->foreignId("service_id")->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId("package_id")->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::table("order_items", function (Blueprint $table) {
            $table->decimal("list_price", 12, 2)->nullable()->after("unit_price");
            $table->foreignId("promotion_id")->nullable()->after("list_price")->constrained()->nullOnDelete();
        });

        Schema::table("orders", function (Blueprint $table) {
            $table->boolean("is_express")->default(false)->after("subtotal");
            $table->decimal("express_fee", 12, 2)->default(0)->after("is_express");
        });
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->dropColumn(["is_express", "express_fee"]);
        });

        Schema::table("order_items", function (Blueprint $table) {
            $table->dropConstrainedForeignId("promotion_id");
            $table->dropColumn("list_price");
        });

        Schema::dropIfExists("promotion_items");
        Schema::dropIfExists("promotions");
    }
};
