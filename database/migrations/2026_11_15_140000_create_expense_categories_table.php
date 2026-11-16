<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("expense_categories", function (Blueprint $table) {
            $table->id();
            $table->string("key", 50)->unique();
            $table->string("name", 100);
            $table->string("color", 20)->default("secondary");
            $table->boolean("is_fixed")->default(false);
            $table->decimal("default_amount", 12, 0)->default(0);
            $table->boolean("is_salary")->default(false);
            $table->timestamps();
        });

        DB::table("expense_categories")->insert([
            ["key" => "detergent", "name" => "Detergente", "color" => "info", "is_fixed" => false, "default_amount" => 0, "is_salary" => false, "created_at" => now(), "updated_at" => now()],
            ["key" => "electricity", "name" => "Eletricidade", "color" => "warning", "is_fixed" => false, "default_amount" => 0, "is_salary" => false, "created_at" => now(), "updated_at" => now()],
            ["key" => "water", "name" => "Água", "color" => "primary", "is_fixed" => false, "default_amount" => 0, "is_salary" => false, "created_at" => now(), "updated_at" => now()],
            ["key" => "gaji", "name" => "Salário", "color" => "success", "is_fixed" => false, "default_amount" => 0, "is_salary" => true, "created_at" => now(), "updated_at" => now()],
            ["key" => "lainnya", "name" => "Outros", "color" => "secondary", "is_fixed" => false, "default_amount" => 0, "is_salary" => false, "created_at" => now(), "updated_at" => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists("expense_categories");
    }
};
