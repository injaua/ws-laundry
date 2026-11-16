<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("equipments", function (Blueprint $table) {
            $table->id();
            $table->string("name", 200);
            $table->string("code", 50)->nullable()->unique();
            $table->string("type", 30)->default("other");
            $table->string("brand", 100)->nullable();
            $table->string("model", 100)->nullable();
            $table->string("serial_number", 100)->nullable();
            $table->date("purchase_date")->nullable();
            $table->decimal("purchase_price", 12, 0)->nullable();
            $table->string("supplier", 150)->nullable();
            $table->date("warranty_until")->nullable();
            $table->string("location", 150)->nullable();
            $table->string("status", 30)->default("operational");
            $table->string("photo")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("equipments");
    }
};
