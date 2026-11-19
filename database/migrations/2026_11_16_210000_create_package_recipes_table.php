<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("package_recipes", function (Blueprint $table) {
            $table->id();
            $table->foreignId("package_id")->constrained()->cascadeOnDelete();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->decimal("quantity", 8, 2);
            $table->string("basis", 10)->default("per_kg");
            $table->string("stage", 10)->default("washing");
            $table->timestamps();
            $table->unique(["package_id", "inventory_item_id", "stage"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("package_recipes");
    }
};
