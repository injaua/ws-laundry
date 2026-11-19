<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("conversations", function (Blueprint $table) {
            $table->id();
            $table->string("type", 10)->default("direct");
            $table->string("title", 150)->nullable();
            $table->foreignId("created_by")->constrained("users")->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create("conversation_user", function (Blueprint $table) {
            $table->id();
            $table->foreignId("conversation_id")->constrained()->cascadeOnDelete();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->timestamp("last_read_at")->nullable();
            $table->unique(["conversation_id", "user_id"]);
        });

        Schema::create("chat_messages", function (Blueprint $table) {
            $table->id();
            $table->foreignId("conversation_id")->constrained()->cascadeOnDelete();
            $table->foreignId("user_id")->constrained("users")->cascadeOnDelete();
            $table->text("body");
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("chat_messages");
        Schema::dropIfExists("conversation_user");
        Schema::dropIfExists("conversations");
    }
};
