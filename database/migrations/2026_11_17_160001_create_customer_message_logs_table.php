<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("customer_message_logs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("customer_id")->constrained()->cascadeOnDelete();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignId("template_id")->nullable()->constrained("message_templates")->nullOnDelete();
            $table->foreignId("order_id")->nullable()->constrained("orders")->nullOnDelete();
            $table->text("body");
            $table->string("phone", 30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("customer_message_logs");
    }
};
