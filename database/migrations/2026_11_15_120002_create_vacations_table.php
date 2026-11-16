<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("vacations", function (Blueprint $table) {
            $table->id();
            $table->foreignId("employee_id")->constrained()->cascadeOnDelete();
            $table->date("start_date");
            $table->date("end_date");
            $table->smallInteger("days")->default(0);
            $table->string("type", 20)->default("vacation");
            $table->string("status", 20)->default("pending");
            $table->foreignId("reviewed_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("reviewed_at")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("vacations");
    }
};
