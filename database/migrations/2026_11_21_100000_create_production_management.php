<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production management:
 *  1. stage history of every order (time per stage, who did it) and delay handling;
 *  2. machine loads (which machine washed/dried which orders, capacity, cycles),
 *     per-load recipes and maintenance by cycles;
 *  3. intake inspection (care instructions, photos), quality control, rework and
 *     complaints linked to the stage / load / operator.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        "Encomenda atrasada" => "Olá {customer}, pedimos desculpa: a sua encomenda {order} vai atrasar. Nova data prevista: {delivery_date}. Obrigado pela compreensão!",
    ];

    public function up(): void
    {
        Schema::create("order_stage_logs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_id")->constrained()->cascadeOnDelete();
            $table->string("from_status", 20)->nullable();
            $table->string("to_status", 20);
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("employee_id")->nullable()->constrained()->nullOnDelete(); // who did the work
            $table->string("note", 255)->nullable();
            $table->timestamp("created_at")->useCurrent();
            $table->index(["to_status", "created_at"]);
        });

        Schema::table("orders", function (Blueprint $table) {
            $table->json("care_flags")->nullable()->after("notes");
            $table->text("intake_notes")->nullable()->after("care_flags");
            $table->date("original_delivery_date")->nullable()->after("delivery_date");
            $table->string("delay_reason", 255)->nullable()->after("original_delivery_date");
            $table->timestamp("qc_passed_at")->nullable()->after("ready_at");
            $table->foreignId("qc_by")->nullable()->after("qc_passed_at")->constrained("users")->nullOnDelete();
            $table->unsignedInteger("rework_count")->default(0)->after("qc_by");
            $table->foreignId("rework_of_order_id")->nullable()->after("rework_count")->constrained("orders")->nullOnDelete();
        });

        Schema::create("order_photos", function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_id")->constrained()->cascadeOnDelete();
            $table->string("path");
            $table->string("caption", 150)->nullable();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table("equipments", function (Blueprint $table) {
            $table->decimal("capacity_kg", 8, 2)->nullable()->after("location");
            $table->unsignedInteger("cycles_count")->default(0)->after("capacity_kg");
            $table->unsignedInteger("service_interval_cycles")->nullable()->after("cycles_count");
            $table->unsignedInteger("cycles_at_last_service")->default(0)->after("service_interval_cycles");
        });

        Schema::create("equipment_recipes", function (Blueprint $table) {
            $table->id();
            $table->foreignId("equipment_id")->constrained("equipments")->cascadeOnDelete();
            $table->foreignId("inventory_item_id")->constrained()->cascadeOnDelete();
            $table->decimal("quantity", 12, 3);   // per cycle (load)
            $table->timestamps();
            $table->unique(["equipment_id", "inventory_item_id"]);
        });

        Schema::create("machine_loads", function (Blueprint $table) {
            $table->id();
            $table->string("code", 30)->unique();
            $table->foreignId("equipment_id")->nullable()->constrained("equipments")->nullOnDelete();
            $table->string("stage", 20);               // washing | drying
            $table->string("program", 60)->nullable();
            $table->decimal("weight_kg", 8, 2)->default(0);
            $table->foreignId("employee_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->string("status", 15)->default("in_progress"); // in_progress | done | cancelled
            $table->timestamp("started_at")->nullable();
            $table->timestamp("finished_at")->nullable();
            $table->string("overload_note", 255)->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index(["status", "started_at"]);
        });

        Schema::create("machine_load_order", function (Blueprint $table) {
            $table->id();
            $table->foreignId("machine_load_id")->constrained()->cascadeOnDelete();
            $table->foreignId("order_id")->constrained()->cascadeOnDelete();
            $table->decimal("weight_kg", 8, 2)->default(0);
            $table->unique(["machine_load_id", "order_id"]);
        });

        Schema::create("order_reworks", function (Blueprint $table) {
            $table->id();
            $table->foreignId("order_id")->constrained()->cascadeOnDelete();
            $table->string("from_status", 20);
            $table->string("reason", 255);
            $table->string("source", 15)->default("qc");  // qc | manual | complaint
            $table->foreignId("complaint_id")->nullable()->constrained()->nullOnDelete();
            $table->foreignId("user_id")->nullable()->constrained()->nullOnDelete();
            $table->timestamp("created_at")->useCurrent();
        });

        Schema::table("complaints", function (Blueprint $table) {
            $table->string("stage", 20)->nullable()->after("category");
            $table->foreignId("machine_load_id")->nullable()->after("stage")->constrained()->nullOnDelete();
            $table->foreignId("employee_id")->nullable()->after("machine_load_id")->constrained()->nullOnDelete();
        });

        // Existing orders start their history at their current stage.
        DB::table("orders")->whereNull("deleted_at")->orderBy("id")->select(["id", "status", "created_at"])
            ->chunk(500, function ($orders) {
                DB::table("order_stage_logs")->insert($orders->map(fn ($o) => [
                    "order_id" => $o->id,
                    "from_status" => null,
                    "to_status" => $o->status,
                    "note" => "initial",
                    "created_at" => $o->created_at ?? now(),
                ])->all());
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

        Schema::table("complaints", function (Blueprint $table) {
            $table->dropConstrainedForeignId("employee_id");
            $table->dropConstrainedForeignId("machine_load_id");
            $table->dropColumn("stage");
        });

        Schema::dropIfExists("order_reworks");
        Schema::dropIfExists("machine_load_order");
        Schema::dropIfExists("machine_loads");
        Schema::dropIfExists("equipment_recipes");

        Schema::table("equipments", function (Blueprint $table) {
            $table->dropColumn(["capacity_kg", "cycles_count", "service_interval_cycles", "cycles_at_last_service"]);
        });

        Schema::dropIfExists("order_photos");

        Schema::table("orders", function (Blueprint $table) {
            $table->dropConstrainedForeignId("rework_of_order_id");
            $table->dropColumn("rework_count");
            $table->dropConstrainedForeignId("qc_by");
            $table->dropColumn(["qc_passed_at", "delay_reason", "original_delivery_date", "intake_notes", "care_flags"]);
        });

        Schema::dropIfExists("order_stage_logs");
    }
};
