<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Piece-by-piece tracking: every garment of an order gets its own code (printed on a
 * tag) and is scanned at each production stage, leaving a full history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("order_pieces", function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_id")->constrained()->cascadeOnDelete();
            $table->foreignId("order_item_id")->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger("seq");
            $table->string("code", 40)->unique();
            $table->string("description", 120);
            $table->string("color", 40)->nullable();
            $table->string("brand", 60)->nullable();
            // Stains, tears, missing buttons… noted at reception, before any work.
            $table->text("intake_notes")->nullable();
            // A problem found later (stain did not come out, piece not found…).
            $table->string("issue", 255)->nullable();
            $table->string("status", 20)->default("received");
            $table->timestamp("status_at")->nullable();
            $table->timestamps();

            $table->unique(["order_id", "seq"]);
            $table->index("status");
        });

        Schema::create("order_piece_events", function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_piece_id")->constrained()->cascadeOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->string("from_status", 20)->nullable();
            $table->string("to_status", 20);
            $table->string("note", 255)->nullable();
            $table->timestamp("created_at")->useCurrent();

            $table->index(["order_piece_id", "id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("order_piece_events");
        Schema::dropIfExists("order_pieces");
    }
};
