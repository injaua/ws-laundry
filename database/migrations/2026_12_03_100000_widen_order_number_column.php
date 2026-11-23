<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Order numbers now carry the store code and a four-digit year
     * (ORD{storeCode}{ddmmyyyy}{NNN}), which no longer fits in 20 characters.
     */
    public function up(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->string("order_number", 40)->change();
        });
    }

    public function down(): void
    {
        Schema::table("orders", function (Blueprint $table) {
            $table->string("order_number", 20)->change();
        });
    }
};
