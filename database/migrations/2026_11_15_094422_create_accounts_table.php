<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("accounts", function (Blueprint $table) {
            $table->id();
            $table->string("code", 10)->unique();
            $table->string("name", 120);
            $table->string("type", 20);
            $table->boolean("is_group")->default(false);
            $table->boolean("is_active")->default(true);
            $table->foreignId("parent_id")->nullable()->constrained("accounts")->nullOnDelete();
            $table->timestamps();
        });

        // SNC (Mozambique) classes — explicit ids 1..8 so children reference them by class number
        DB::table("accounts")->insert([
            ["id" => 1, "code" => "1", "name" => "Meios líquidos", "type" => "asset", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 2, "code" => "2", "name" => "Contas correntes", "type" => "asset", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 3, "code" => "3", "name" => "Investimentos", "type" => "asset", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 4, "code" => "4", "name" => "Gastos e perdas", "type" => "expense", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 5, "code" => "5", "name" => "Vendas e prestações de serviços", "type" => "revenue", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 6, "code" => "6", "name" => "Outros gastos e proveitos", "type" => "expense", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 7, "code" => "7", "name" => "Rendas, alvarás e taxas", "type" => "expense", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
            ["id" => 8, "code" => "8", "name" => "Resultados", "type" => "equity", "is_group" => true, "is_active" => true, "parent_id" => null, "created_at" => now(), "updated_at" => now()],
        ]);

        DB::table("accounts")->insert([
            ["code" => "11", "name" => "Caixa", "type" => "asset", "is_group" => false, "parent_id" => 1, "created_at" => now(), "updated_at" => now()],
            ["code" => "12", "name" => "Depósitos à ordem", "type" => "asset", "is_group" => false, "parent_id" => 1, "created_at" => now(), "updated_at" => now()],
            ["code" => "13", "name" => "Outros meios líquidos", "type" => "asset", "is_group" => false, "parent_id" => 1, "created_at" => now(), "updated_at" => now()],
            ["code" => "21", "name" => "Clientes", "type" => "asset", "is_group" => false, "parent_id" => 2, "created_at" => now(), "updated_at" => now()],
            ["code" => "22", "name" => "Fornecedores", "type" => "liability", "is_group" => false, "parent_id" => 2, "created_at" => now(), "updated_at" => now()],
            ["code" => "23", "name" => "Adiantamentos e cauções", "type" => "liability", "is_group" => false, "parent_id" => 2, "created_at" => now(), "updated_at" => now()],
            ["code" => "31", "name" => "Investimentos tangíveis", "type" => "asset", "is_group" => false, "parent_id" => 3, "created_at" => now(), "updated_at" => now()],
            ["code" => "32", "name" => "Investimentos intangíveis", "type" => "asset", "is_group" => false, "parent_id" => 3, "created_at" => now(), "updated_at" => now()],
            ["code" => "33", "name" => "Investimentos financeiros", "type" => "asset", "is_group" => false, "parent_id" => 3, "created_at" => now(), "updated_at" => now()],
            ["code" => "41", "name" => "Fornecimentos e serviços de terceiros", "type" => "expense", "is_group" => false, "parent_id" => 4, "created_at" => now(), "updated_at" => now()],
            ["code" => "42", "name" => "Gastos com o pessoal", "type" => "expense", "is_group" => false, "parent_id" => 4, "created_at" => now(), "updated_at" => now()],
            ["code" => "44", "name" => "Gastos e perdas financeiros", "type" => "expense", "is_group" => false, "parent_id" => 4, "created_at" => now(), "updated_at" => now()],
            ["code" => "45", "name" => "Outros gastos e perdas", "type" => "expense", "is_group" => false, "parent_id" => 4, "created_at" => now(), "updated_at" => now()],
            ["code" => "51", "name" => "Prestação de serviços", "type" => "revenue", "is_group" => false, "parent_id" => 5, "created_at" => now(), "updated_at" => now()],
            ["code" => "52", "name" => "Venda de mercadorias e pacotes", "type" => "revenue", "is_group" => false, "parent_id" => 5, "created_at" => now(), "updated_at" => now()],
            ["code" => "53", "name" => "Descontos e abatimentos", "type" => "revenue", "is_group" => false, "parent_id" => 5, "created_at" => now(), "updated_at" => now()],
            ["code" => "61", "name" => "Outros gastos", "type" => "expense", "is_group" => false, "parent_id" => 6, "created_at" => now(), "updated_at" => now()],
            ["code" => "71", "name" => "Imposto industrial e outras taxas", "type" => "expense", "is_group" => false, "parent_id" => 7, "created_at" => now(), "updated_at" => now()],
            ["code" => "81", "name" => "Resultados transitados", "type" => "equity", "is_group" => false, "parent_id" => 8, "created_at" => now(), "updated_at" => now()],
            ["code" => "82", "name" => "Resultado líquido do período", "type" => "equity", "is_group" => false, "parent_id" => 8, "created_at" => now(), "updated_at" => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists("accounts");
    }
};
