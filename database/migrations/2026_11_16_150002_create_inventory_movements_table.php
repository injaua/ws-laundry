<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("inventory_movements", function (Blueprint $table) {
            $table->id();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->string("direction", 10);
            $table->decimal("quantity", 8, 2);
            $table->decimal("unit_cost", 12, 0)->nullable();
            $table->string("reason", 30);
            $table->foreignId("expense_id")->nullable()->constrained("expenses")->nullOnDelete();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->date("moved_at");
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index(["inventory_item_id", "moved_at"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("inventory_movements");
    }
};
