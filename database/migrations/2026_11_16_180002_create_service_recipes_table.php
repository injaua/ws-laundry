<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("service_recipes", function (Blueprint $table) {
            $table->id();
            $table->foreignId("service_id")->constrained()->cascadeOnDelete();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->decimal("quantity", 8, 2);
            $table->string("basis", 10)->default("per_kg");
            $table->timestamps();
            $table->unique(["service_id", "inventory_item_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("service_recipes");
    }
};
