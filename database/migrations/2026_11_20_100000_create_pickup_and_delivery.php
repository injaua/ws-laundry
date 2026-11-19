<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Home pickup & delivery: zones with their fee, courier jobs (pickups and
 * deliveries), the delivery fee on orders and cash collected by couriers that
 * still has to be handed over at the counter.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        "Recolha agendada" => "Olá {customer}! A sua recolha está agendada para {date} ({window}). Morada: {address}. Obrigado pela preferência!",
        "Estafeta a caminho" => "Olá {customer}, o nosso estafeta {courier} está a caminho para {task}. Até já!",
    ];

    public function up(): void
    {
        Schema::create("delivery_zones", function (Blueprint $table) {
            $table->id();
            $table->string("name", 100);
            $table->text("description")->nullable();          // neighbourhoods covered
            $table->decimal("fee", 12, 2)->default(0);           // per trip (pickup or delivery)
            $table->decimal("free_from", 12, 2)->nullable();     // order value from which the trip is free
            $table->boolean("is_active")->default(true);
            $table->unsignedInteger("sort_order")->default(0);
            $table->timestamps();
        });

        Schema::table("customers", function (Blueprint $table) {
            $table->foreignId("delivery_zone_id")->nullable()->after("address")->constrained()->nullOnDelete();
            $table->string("address_reference", 255)->nullable()->after("delivery_zone_id");
        });

        Schema::create("courier_jobs", function (Blueprint $table) {
            $table->id();
            $table->string("type", 10);                          // pickup | delivery
            $table->string("status", 15)->default("scheduled");  // requested | scheduled | on_the_way | done | failed | cancelled
            $table->string("source", 10)->default("counter");    // counter | website
            $table->foreignId("customer_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("order_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("delivery_zone_id")->nullable()->constrained()->nullOnDelete();
            $table->string("contact_name", 100);
            $table->string("contact_phone", 30);
            $table->string("address", 255);
            $table->string("address_reference", 255)->nullable();
            $table->date("scheduled_date");
            $table->string("time_window", 10)->default("any");   // morning | afternoon | evening | any
            $table->foreignId("courier_id")->nullable()->constrained("users")->nullOnDelete();
            $table->decimal("fee", 12, 2)->default(0);
            $table->text("notes")->nullable();
            $table->text("outcome_notes")->nullable();
            $table->string("failure_reason", 255)->nullable();
            $table->timestamp("started_at")->nullable();
            $table->timestamp("completed_at")->nullable();
            $table->timestamp("cancelled_at")->nullable();
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();
            $table->index(["scheduled_date", "status"]);
        });

        Schema::table("orders", function (Blueprint $table) {
            $table->decimal("delivery_fee", 12, 2)->default(0)->after("express_fee");
        });

        Schema::table("payments", function (Blueprint $table) {
            $table->foreignId("courier_job_id")->nullable()->after("invoice_id")->constrained()->nullOnDelete();
            $table->timestamp("handed_over_at")->nullable()->after("courier_job_id");
            $table->foreignId("handed_over_by")->nullable()->after("handed_over_at")->constrained("users")->nullOnDelete();
        });

        foreach (self::TEMPLATES as $name => $body) {
            if (!DB::table("message_templates")->where("name", $name)->exists()) {
                DB::table("message_templates")->insert([
                    "name" => $name, "body" => $body, "is_active" => true,
                    "created_at" => now(), "updated_at" => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table("message_templates")->whereIn("name", array_keys(self::TEMPLATES))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from("whatsapp_messages")->whereColumn("whatsapp_messages.template_id", "message_templates.id"))
            ->delete();

        Schema::table("payments", function (Blueprint $table) {
            $table->dropConstrainedForeignId("handed_over_by");
            $table->dropColumn("handed_over_at");
            $table->dropConstrainedForeignId("courier_job_id");
        });

        Schema::table("orders", function (Blueprint $table) {
            $table->dropColumn("delivery_fee");
        });

        Schema::dropIfExists("courier_jobs");

        Schema::table("customers", function (Blueprint $table) {
            $table->dropColumn("address_reference");
            $table->dropConstrainedForeignId("delivery_zone_id");
        });

        Schema::dropIfExists("delivery_zones");
    }
};
