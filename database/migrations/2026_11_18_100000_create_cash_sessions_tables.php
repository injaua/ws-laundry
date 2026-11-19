<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One till shift per operator: opened with a float, closed with a blind count.
        Schema::create("cash_sessions", function (Blueprint $table) {
            $table->id();
            $table->foreignId("user_id")->constrained("users")->restrictOnDelete();
            $table->string("status", 10)->default("open")->index();
            $table->dateTime("opened_at");
            $table->decimal("opening_float", 12, 2)->default(0);
            $table->dateTime("closed_at")->nullable();
            $table->foreignId("closed_by")->nullable()->constrained("users")->nullOnDelete();
            $table->text("closing_note")->nullable();
            $table->timestamps();

            $table->index(["user_id", "status"]);
        });

        // Expected vs counted per payment method, frozen at closing time.
        Schema::create("cash_session_counts", function (Blueprint $table) {
            $table->id();
            $table->foreignId("cash_session_id")->constrained()->cascadeOnDelete();
            $table->string("method", 20);
            $table->decimal("expected", 12, 2);
            $table->decimal("counted", 12, 2);
            $table->decimal("difference", 12, 2);
            $table->timestamps();

            $table->unique(["cash_session_id", "method"]);
        });

        // Cash taken out to the safe (withdrawal) or added to the till (deposit).
        Schema::create("cash_movements", function (Blueprint $table) {
            $table->id();
            $table->foreignId("cash_session_id")->constrained()->cascadeOnDelete();
            $table->foreignId("user_id")->constrained("users")->restrictOnDelete();
            $table->string("type", 10);
            $table->decimal("amount", 12, 2);
            $table->string("reason", 255);
            $table->timestamps();
        });

        Schema::table("payments", function (Blueprint $table) {
            $table->foreignId("cash_session_id")->nullable()->after("user_id")
                ->constrained("cash_sessions")->nullOnDelete();
        });

        // Cash shortages are booked to "Outros gastos e perdas" (PGC-NIRF 6.8.9.9).
        if (!DB::table("accounts")->where("code", "6.8.9.9")->exists()) {
            DB::table("accounts")->insert([
                "code" => "6.8.9.9",
                "name" => "Outros gastos e perdas",
                "type" => "expense",
                "is_group" => false,
                "is_active" => true,
                "parent_id" => DB::table("accounts")->where("code", "6")->value("id"),
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table("payments", function (Blueprint $table) {
            $table->dropConstrainedForeignId("cash_session_id");
        });

        Schema::dropIfExists("cash_movements");
        Schema::dropIfExists("cash_session_counts");
        Schema::dropIfExists("cash_sessions");

        DB::table("accounts")->where("code", "6.8.9.9")
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from("journal_lines")->whereColumn("journal_lines.account_id", "accounts.id"))
            ->delete();
    }
};
