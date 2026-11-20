<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Head of the audit-trail hash chain: one row with the id and hash of the last entry.
 * ActivityLog::log() locks it before inserting, so concurrent writers (any store) take
 * turns and each entry chains to the committed previous one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("activity_log_chain", function (Blueprint $table) {
            $table->unsignedTinyInteger("id")->primary();
            $table->unsignedBigInteger("last_id")->nullable();
            $table->string("last_hash", 64)->nullable();
        });

        $last = DB::table("activity_logs")->orderByDesc("id")->first(["id", "hash"]);
        DB::table("activity_log_chain")->insert(["id" => 1, "last_id" => $last?->id, "last_hash" => $last?->hash]);
    }

    public function down(): void
    {
        Schema::dropIfExists("activity_log_chain");
    }
};
