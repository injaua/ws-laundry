<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Which channel a WhatsApp message went through: cloud (official API), web (QR) or log. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("whatsapp_messages", function (Blueprint $table) {
            $table->string("channel", 10)->nullable()->after("phone");
        });
    }

    public function down(): void
    {
        Schema::table("whatsapp_messages", function (Blueprint $table) {
            $table->dropColumn("channel");
        });
    }
};
