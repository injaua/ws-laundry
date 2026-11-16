<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("absence_justifications", function (Blueprint $table) {
            $table->id();
            $table->foreignId("employee_id")->constrained()->cascadeOnDelete();
            $table->date("absence_date");
            $table->string("reason", 255);
            $table->string("status", 20)->default("pending");
            $table->foreignId("reviewed_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("reviewed_at")->nullable();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->unique(["employee_id", "absence_date"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("absence_justifications");
    }
};
