<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * After-sales: credit notes (with refunds), customer complaints and the
 * follow-up of garments that were never collected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("complaints", function (Blueprint $table) {
            $table->id();
            $table->string("number", 20)->unique();
            $table->foreignId("customer_id")->constrained();
            $table->foreignId("order_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("order_piece_id")->nullable()->constrained()->nullOnDelete();
            $table->string("channel", 20);
            $table->string("category", 30);
            $table->text("description");
            $table->string("status", 20)->default("open");
            $table->date("due_at");
            $table->string("resolution", 30)->nullable();
            $table->text("resolution_notes")->nullable();
            $table->foreignId("opened_by")->nullable()->constrained("users")->nullOnDelete();
            $table->foreignId("resolved_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("resolved_at")->nullable();
            $table->timestamps();

            $table->index(["status", "due_at"]);
        });

        Schema::create("credit_notes", function (Blueprint $table) {
            $table->id();
            $table->string("number", 20)->unique();
            $table->foreignId("order_id")->constrained();
            $table->foreignId("customer_id")->constrained();
            $table->foreignId("complaint_id")->nullable()->constrained()->nullOnDelete();
            $table->date("issued_at");
            $table->string("reason", 30);
            $table->text("description");
            // Gross amount (VAT included) and its split at the order's VAT rate.
            $table->decimal("amount", 12, 2);
            $table->decimal("net_amount", 12, 2);
            $table->decimal("iva_amount", 12, 2)->default(0);
            $table->decimal("iva_rate", 5, 2)->default(0);
            // refund: money handed back to the customer; deduct: lowers what is still owed.
            $table->string("settlement", 10);
            $table->string("refund_method", 20)->nullable();
            // Whether the order's revenue was already booked when the note was issued.
            $table->boolean("reverses_revenue")->default(false);
            $table->foreignId("cash_session_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            // Fiscal documents are never deleted: a mistaken one is voided, keeping its number.
            $table->timestamp("voided_at")->nullable();
            $table->foreignId("voided_by")->nullable()->constrained("users")->nullOnDelete();
            $table->string("void_reason", 255)->nullable();
            $table->timestamps();

            $table->index(["order_id", "voided_at"]);
            $table->index(["settlement", "issued_at"]);
        });

        Schema::table("orders", function (Blueprint $table) {
            // Garments never collected: when and how the laundry disposed of them.
            $table->timestamp("abandoned_at")->nullable()->after("completed_at");
            $table->string("abandon_destination", 20)->nullable()->after("abandoned_at");
            $table->string("abandon_note", 255)->nullable()->after("abandon_destination");
            $table->foreignId("abandoned_by")->nullable()->after("abandon_note")->constrained("users")->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->dropConstrainedForeignId("abandoned_by");
            $table->dropColumn(["abandoned_at", "abandon_destination", "abandon_note"]);
        });
        Schema::dropIfExists("credit_notes");
        Schema::dropIfExists("complaints");
    }
};
