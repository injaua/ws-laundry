<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who prepared each payslip, so the salary policy can let an accountant change
 * only their own payslips. Existing payslips have no author: only admin and
 * owner may change them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table("employee_salaries", function (Blueprint $table) {
            $table->foreignId("created_by")->nullable()->after("employee_id")->constrained("users")->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table("employee_salaries", function (Blueprint $table) {
            $table->dropConstrainedForeignId("created_by");
        });
    }
};
