<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table("expenses")->where("source_type", "employee_salary")->delete();
        DB::table("employee_salaries")->update(["expense_id" => null]);
    }

    public function down(): void
    {
        // Auto-posted expenses are intentionally not restored.
    }
};
