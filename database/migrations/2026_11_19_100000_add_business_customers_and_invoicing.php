<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business customers: fiscal identity, consolidated (monthly) invoices,
 * grouped payments across orders and negotiated prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("customers", function (Blueprint $table) {
            $table->string("type", 20)->default("individual")->after("name");
            $table->string("legal_name", 150)->nullable()->after("type");
            $table->string("nuit", 20)->nullable()->after("legal_name");
            $table->string("email", 150)->nullable()->after("phone");
            $table->string("contact_person", 100)->nullable()->after("email");
            $table->text("billing_address")->nullable()->after("address");
            $table->string("billing_cycle", 20)->default("per_order")->after("credit_terms_days");
            $table->decimal("discount_percent", 5, 2)->default(0)->after("billing_cycle");
        });

        Schema::create("invoices", function (Blueprint $table) {
            $table->id();
            $table->string("number", 30)->nullable()->unique();
            $table->foreignId("customer_id")->constrained()->restrictOnDelete();
            $table->date("period_start");
            $table->date("period_end");
            $table->date("issue_date")->nullable();
            $table->date("due_date")->nullable();
            $table->decimal("subtotal", 12, 2)->default(0);
            $table->decimal("vat_amount", 12, 2)->default(0);
            $table->decimal("total", 12, 2)->default(0);
            $table->string("status", 20)->default("draft");
            $table->text("notes")->nullable();
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("issued_at")->nullable();
            $table->timestamp("voided_at")->nullable();
            $table->string("void_reason", 255)->nullable();
            $table->timestamps();

            $table->index(["customer_id", "status"]);
        });

        Schema::table("orders", function (Blueprint $table) {
            $table->string("purchase_order_ref", 60)->nullable()->after("credit_override_note");
            $table->foreignId("invoice_id")->nullable()->after("purchase_order_ref")->constrained()->nullOnDelete();
        });

        Schema::table("payments", function (Blueprint $table) {
            $table->string("group_ref", 40)->nullable()->after("note")->index();
            $table->foreignId("invoice_id")->nullable()->after("group_ref")->constrained()->nullOnDelete();
        });

        Schema::create("customer_prices", function (Blueprint $table) {
            $table->id();
            $table->foreignId("customer_id")->constrained()->cascadeOnDelete();
            $table->foreignId("service_id")->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId("package_id")->nullable()->constrained()->cascadeOnDelete();
            $table->decimal("price", 12, 2);
            $table->timestamps();

            $table->unique(["customer_id", "service_id", "package_id"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("customer_prices");

        Schema::table("payments", function (Blueprint $table) {
            $table->dropConstrainedForeignId("invoice_id");
            $table->dropIndex(["group_ref"]);
            $table->dropColumn("group_ref");
        });

        Schema::table("orders", function (Blueprint $table) {
            $table->dropConstrainedForeignId("invoice_id");
            $table->dropColumn("purchase_order_ref");
        });

        Schema::dropIfExists("invoices");

        Schema::table("customers", function (Blueprint $table) {
            $table->dropColumn([
                "type", "legal_name", "nuit", "email", "contact_person",
                "billing_address", "billing_cycle", "discount_percent",
            ]);
        });
    }
};
