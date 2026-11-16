<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("employee_salaries", function (Blueprint $table) {
            $table->id();
            $table->foreignId("employee_id")->constrained()->cascadeOnDelete();
            $table->smallInteger("period_year");
            $table->tinyInteger("period_month");
            $table->decimal("base_salary", 12, 0)->default(0);
            $table->decimal("bonus", 12, 0)->default(0);
            $table->decimal("deduction", 12, 0)->default(0);
            $table->decimal("net_salary", 12, 0)->default(0);
            $table->string("status", 20)->default("pending");
            $table->timestamp("paid_at")->nullable();
            $table->foreignId("expense_id")->nullable()->constrained("expenses")->nullOnDelete();
            $table->text("notes")->nullable();
            $table->timestamps();
            $table->unique(["employee_id", "period_year", "period_month"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("employee_salaries");
    }
};
