<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("journal_entries", function (Blueprint $table) {
            $table->id();
            $table->string("entry_number", 20)->unique();
            $table->date("entry_date");
            $table->string("description", 255);
            $table->string("source", 20)->default("manual");
            $table->unsignedBigInteger("source_id")->nullable();
            $table->foreignId("posted_by")->nullable()->constrained("users")->nullOnDelete();
            $table->timestamps();

            $table->index(["source", "source_id"]);
        });

        Schema::create("journal_lines", function (Blueprint $table) {
            $table->id();
            $table->foreignId("journal_entry_id")->constrained()->cascadeOnDelete();
            $table->foreignId("account_id")->constrained("accounts")->restrictOnDelete();
            $table->decimal("debit", 12, 0)->default(0);
            $table->decimal("credit", 12, 0)->default(0);
            $table->string("description", 255)->nullable();
            $table->timestamps();

            $table->index("account_id");
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("journal_lines");
        Schema::dropIfExists("journal_entries");
    }
};
