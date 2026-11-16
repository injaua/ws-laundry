<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("maintenance_jobs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("equipment_id")->constrained("equipments")->cascadeOnDelete();
            $table->string("kind", 20)->default("corrective");
            $table->string("title", 200);
            $table->text("description")->nullable();
            $table->text("fault_description")->nullable();
            $table->string("technician", 150)->nullable();
            $table->foreignId("employee_id")->nullable()->constrained()->nullOnDelete();
            $table->date("service_date");
            $table->decimal("cost", 12, 0)->default(0);
            $table->foreignId("expense_id")->nullable()->constrained("expenses")->nullOnDelete();
            $table->string("status", 20)->default("scheduled");
            $table->date("next_service_date")->nullable();
            $table->timestamp("completed_at")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("maintenance_jobs");
    }
};
