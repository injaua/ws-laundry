<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("inventory_items", function (Blueprint $table) {
            $table->id();
            $table->string("kind", 20);
            $table->string("name", 200);
            $table->string("type", 50)->nullable();
            $table->string("unit", 10)->default("un");
            $table->decimal("stock_qty", 8, 2)->default(0);
            $table->decimal("min_stock_qty", 8, 2)->default(0);
            $table->decimal("unit_cost", 12, 0)->default(0);
            $table->string("supplier", 150)->nullable();
            $table->boolean("is_active")->default(true);
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index(["kind", "name"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("inventory_items");
    }
};
