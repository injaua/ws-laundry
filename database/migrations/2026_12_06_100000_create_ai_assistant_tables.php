<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI assistant: conversations (questions and answers, with the tools each answer used),
     * automatic insights (store alerts and briefings, and the company briefing for the admin),
     * and a usage log of every call to the AI provider (tokens and cost, for the monthly budget).
     *
     * store_id is null for the company level (the admin's company briefing and usage).
     */
    public function up(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->boolean("ai_insights_enabled")->default(true)->after("is_active");
        });

        Schema::create("ai_conversations", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->string("title");
            $table->timestamps();
            $table->index(["user_id", "updated_at"]);
        });

        Schema::create("ai_messages", function (Blueprint $table) {
            $table->id();
            $table->foreignId("conversation_id")->constrained("ai_conversations")->cascadeOnDelete();
            $table->string("role", 12);
            $table->longText("content");
            $table->json("tools")->nullable();
            $table->boolean("failed")->default(false);
            $table->timestamps();
        });

        Schema::create("ai_tool_calls", function (Blueprint $table) {
            $table->id();
            $table->foreignId("conversation_id")->nullable()->constrained("ai_conversations")->cascadeOnDelete();
            $table->foreignId("message_id")->nullable()->constrained("ai_messages")->nullOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->string("tool", 60);
            $table->json("input")->nullable();
            $table->string("status", 12);
            $table->unsignedInteger("duration_ms")->default(0);
            $table->timestamp("created_at")->nullable();
        });

        Schema::create("ai_runs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->string("purpose", 20);
            $table->string("provider", 30);
            $table->string("model", 100);
            $table->unsignedInteger("input_tokens")->default(0);
            $table->unsignedInteger("output_tokens")->default(0);
            $table->decimal("cost", 12, 6)->default(0);
            $table->boolean("succeeded")->default(true);
            $table->string("error", 500)->nullable();
            $table->timestamp("created_at")->nullable();
            $table->index(["store_id", "created_at"]);
        });

        Schema::create("ai_insights", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->string("audience", 10);
            $table->string("kind", 12);
            $table->string("detector", 40)->nullable();
            $table->string("severity", 10);
            $table->string("title");
            $table->text("body");
            $table->text("recommendation")->nullable();
            $table->json("data")->nullable();
            $table->string("link")->nullable();
            $table->string("fingerprint", 120);
            $table->date("period_date");
            $table->string("status", 10)->default("new");
            $table->boolean("ai_written")->default(false);
            $table->timestamps();
            $table->index(["audience", "store_id", "status", "created_at"]);
            $table->index(["fingerprint"]);
        });

        Schema::create("ai_insight_feedback", function (Blueprint $table) {
            $table->id();
            $table->foreignId("insight_id")->constrained("ai_insights")->cascadeOnDelete();
            $table->foreignId("user_id")->constrained()->cascadeOnDelete();
            $table->boolean("useful");
            $table->timestamps();
            $table->unique(["insight_id", "user_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("ai_insight_feedback");
        Schema::dropIfExists("ai_insights");
        Schema::dropIfExists("ai_runs");
        Schema::dropIfExists("ai_tool_calls");
        Schema::dropIfExists("ai_messages");
        Schema::dropIfExists("ai_conversations");
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn("ai_insights_enabled");
        });
    }
};
