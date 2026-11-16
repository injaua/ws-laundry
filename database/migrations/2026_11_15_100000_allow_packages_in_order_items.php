<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('service_id')
                ->constrained('packages')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->change();
            $table->enum('type', ['by_weight', 'by_piece', 'by_package'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->enum('type', ['by_weight', 'by_piece'])->change();
            $table->foreignId('service_id')->nullable(false)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });
    }
};
