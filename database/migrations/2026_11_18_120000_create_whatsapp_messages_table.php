<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every WhatsApp message in or out, grouped into conversations by phone number.
        Schema::create("whatsapp_messages", function (Blueprint $table) {
            $table->id();
            $table->string("phone", 20)->index();
            $table->foreignId("customer_id")->nullable()->constrained()->nullOnDelete();
            $table->string("direction", 3); // in | out
            $table->string("type", 20)->default("text"); // text | template | image | audio | document | …
            $table->text("body")->nullable();
            $table->string("template_name", 120)->nullable();
            $table->json("meta")->nullable(); // template language + parameters, media info
            // queued | sent | delivered | read | failed | received | manual (legacy wa.me link)
            $table->string("status", 12)->default("queued");
            $table->string("provider_message_id", 191)->nullable()->unique();
            $table->string("error", 500)->nullable();
            $table->foreignId("user_id")->nullable()->constrained("users")->nullOnDelete();
            $table->foreignId("order_id")->nullable()->constrained("orders")->nullOnDelete();
            $table->foreignId("template_id")->nullable()->constrained("message_templates")->nullOnDelete();
            // Automatic notifications: e.g. "order_ready:42" — guarantees each is sent once.
            $table->string("notification_key", 120)->nullable()->unique();
            $table->timestamp("read_by_staff_at")->nullable();
            $table->timestamp("status_at")->nullable();
            $table->timestamps();

            $table->index(["phone", "id"]);
        });

        Schema::table("customers", function (Blueprint $table) {
            $table->boolean("whatsapp_opt_out")->default(false)->after("phone");
        });

        // When the order became ready: drives the "items not collected" reminders.
        Schema::table("orders", function (Blueprint $table) {
            $table->timestamp("ready_at")->nullable()->after("delivery_date");
        });
        DB::table("orders")->where("status", "ready")->update(["ready_at" => DB::raw("updated_at")]);

        // Link a local template to the one approved by Meta (needed to start a conversation).
        Schema::table("message_templates", function (Blueprint $table) {
            $table->string("wa_template_name", 120)->nullable()->after("body");
            $table->string("wa_language", 10)->nullable()->after("wa_template_name");
            $table->json("wa_params")->nullable()->after("wa_language");
        });

        // Keep the history of messages sent by hand through wa.me links.
        DB::table("customer_message_logs")->orderBy("id")->each(function ($log) {
            DB::table("whatsapp_messages")->insert([
                "phone" => $log->phone,
                "customer_id" => $log->customer_id,
                "direction" => "out",
                "type" => "text",
                "body" => $log->body,
                "status" => "manual",
                "user_id" => $log->user_id,
                "order_id" => $log->order_id,
                "template_id" => $log->template_id,
                "read_by_staff_at" => $log->created_at,
                "status_at" => $log->created_at,
                "created_at" => $log->created_at,
                "updated_at" => $log->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table("message_templates", function (Blueprint $table) {
            $table->dropColumn(["wa_template_name", "wa_language", "wa_params"]);
        });
        Schema::table("orders", function (Blueprint $table) {
            $table->dropColumn("ready_at");
        });
        Schema::table("customers", function (Blueprint $table) {
            $table->dropColumn("whatsapp_opt_out");
        });
        Schema::dropIfExists("whatsapp_messages");
    }
};
