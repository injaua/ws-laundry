<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("accounting_periods", function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger("year");
            $table->unsignedTinyInteger("month");
            $table->string("status", 10)->default("closed");
            $table->foreignId("closed_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("closed_at")->nullable();
            $table->foreignId("reopened_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamp("reopened_at")->nullable();
            $table->string("notes", 255)->nullable();
            $table->timestamps();
            $table->unique(["year", "month"]);
        });

        Schema::create("balancete_archives", function (Blueprint $table) {
            $table->id();
            $table->foreignId("accounting_period_id")->constrained("accounting_periods")->cascadeOnDelete();
            $table->unsignedSmallInteger("year");
            $table->unsignedTinyInteger("month");
            $table->date("from_date");
            $table->date("to_date");
            $table->decimal("total_debit", 12, 0)->default(0);
            $table->decimal("total_credit", 12, 0)->default(0);
            $table->foreignId("created_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();
        });

        Schema::create("balancete_archive_lines", function (Blueprint $table) {
            $table->id();
            $table->foreignId("balancete_archive_id")->constrained("balancete_archives")->cascadeOnDelete();
            $table->string("account_code", 20);
            $table->string("account_name", 200);
            $table->string("account_type", 20);
            $table->decimal("total_debit", 12, 0)->default(0);
            $table->decimal("total_credit", 12, 0)->default(0);
            $table->decimal("balance_debit", 12, 0)->default(0);
            $table->decimal("balance_credit", 12, 0)->default(0);
            $table->index(["balancete_archive_id", "account_code"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("balancete_archive_lines");
        Schema::dropIfExists("balancete_archives");
        Schema::dropIfExists("accounting_periods");
    }
};
