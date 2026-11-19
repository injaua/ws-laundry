<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counters behind every numbered series (orders, invoices, credit notes, complaints,
 * machine loads), one row per store and series prefix. NumberSequence increments a row
 * inside the transaction that saves the record, so concurrent saves are serialised by
 * the row lock and a rolled-back save gives its number back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create("number_sequences", function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("store_id")->default(0);
            $table->string("series", 40);
            $table->unsignedBigInteger("last_value")->default(0);
            $table->timestamps();

            $table->unique(["store_id", "series"]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("number_sequences");
    }
};
