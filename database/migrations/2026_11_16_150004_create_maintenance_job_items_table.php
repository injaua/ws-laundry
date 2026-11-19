<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("maintenance_job_items", function (Blueprint $table) {
            $table->id();
            $table->foreignId("maintenance_job_id")->constrained()->cascadeOnDelete();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->decimal("quantity", 8, 2);
            $table->foreignId("inventory_movement_id")->nullable();
            $table->timestamps();
            // Explicit name: the generated one is over the 64-character MySQL limit.
            $table->unique(["maintenance_job_id", "inventory_item_id"], "maintenance_job_items_job_item_unique");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("maintenance_job_items");
    }
};
