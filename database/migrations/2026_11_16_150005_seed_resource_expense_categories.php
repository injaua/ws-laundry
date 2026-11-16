<?php

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defs = [
            ["key" => "produtos", "name" => "Produtos de limpeza", "color" => "info", "account" => "6.1.1", "vat" => true],
            ["key" => "manutencao", "name" => "Manutenção e assistência", "color" => "warning", "account" => "6.3.2.9.9", "vat" => false],
        ];

        foreach ($defs as $def) {
            if (ExpenseCategory::firstWhere("key", $def["key"])) {
                continue;
            }

            ExpenseCategory::create([
                "key" => $def["key"],
                "name" => $def["name"],
                "color" => $def["color"],
                "account_id" => Account::findByCode($def["account"])?->id,
                "is_vat_applicable" => $def["vat"],
            ]);
        }
    }

    public function down(): void
    {
        ExpenseCategory::whereIn("key", ["produtos", "manutencao"])->delete();
    }
};
