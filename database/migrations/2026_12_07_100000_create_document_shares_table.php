<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Document exchange between admin, owner and accountant: a document of the archive is sent
     * to one or more colleagues (received / sent boxes, read receipt). A document sent as
     * private is seen only by whoever uploaded it and the people it was sent to.
     */
    public function up(): void
    {
        Schema::table("documents", function (Blueprint $table) {
            $table->boolean("is_private")->default(false)->after("uploaded_by");
        });

        Schema::create("document_shares", function (Blueprint $table) {
            $table->id();
            $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            $table->foreignId("document_id")->constrained()->cascadeOnDelete();
            $table->foreignId("sender_id")->nullable()->constrained("users")->nullOnDelete();
            $table->foreignId("recipient_id")->constrained("users")->cascadeOnDelete();
            $table->text("message")->nullable();
            $table->timestamp("read_at")->nullable();
            $table->timestamps();
            $table->index(["recipient_id", "read_at"]);
            $table->index(["sender_id", "created_at"]);
            $table->index(["document_id", "recipient_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("document_shares");
        Schema::table("documents", function (Blueprint $table) {
            $table->dropColumn("is_private");
        });
    }
};
