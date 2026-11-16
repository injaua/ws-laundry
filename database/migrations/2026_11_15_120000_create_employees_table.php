<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("employees", function (Blueprint $table) {
            $table->id();
            $table->string("name", 100);
            $table->string("bi_number", 30)->nullable();
            $table->string("position", 100);
            $table->string("phone", 30)->nullable();
            $table->text("address")->nullable();
            $table->date("birth_date")->nullable();
            $table->date("hire_date");
            $table->date("probation_ends_at")->nullable();
            $table->string("status", 20)->default("probation");
            $table->string("contract_type", 20)->default("full_time");
            $table->decimal("base_salary", 12, 0)->default(0);
            $table->date("termination_date")->nullable();
            $table->text("termination_reason")->nullable();
            $table->foreignId("user_id")->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->index("status");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("employees");
    }
};
