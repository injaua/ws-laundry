<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PGC-NIRF / SCE (Decreto n.º 70/2009) chart, scoped to a medium laundry.
     * Clean rebuild: existing SNC accounts and their journal entries are discarded.
     */
    public function up(): void
    {
        DB::table("expense_categories")->update(["account_id" => null]);
        DB::table("journal_lines")->delete();
        DB::table("journal_entries")->delete();
        DB::table("accounts")->delete();

        $classes = [
            ["1", "Meios financeiros", "asset"],
            ["2", "Inventários", "asset"],
            ["3", "Investimentos de capital", "asset"],
            ["4", "Contas a receber e a pagar, acréscimos e diferimentos", "liability"],
            ["5", "Capital próprio", "equity"],
            ["6", "Gastos e perdas", "expense"],
            ["7", "Rendimentos e ganhos", "revenue"],
            ["8", "Resultados", "equity"],
        ];

        $this->insertAccounts($classes, true);

        $groups = [
            ["1.2", "Bancos", "asset", "1"],
        ];
        $this->insertAccounts($groups, true);

        $ids = DB::table("accounts")->pluck("id", "code");

        $leaves = [
            ["1.1", "Caixa", "asset", "1"],
            ["1.2.1", "Depósitos à ordem", "asset", "1.2"],
            ["2.2", "Mercadorias", "asset", "2"],
            ["2.6.2", "Matérias-primas, auxiliares e materiais", "asset", "2"],
            ["3.2.2", "Equipamento básico", "asset", "3"],
            ["3.2.3", "Mobiliário e equipamento administrativo", "asset", "3"],
            ["3.2.4", "Equipamento de transporte", "asset", "3"],
            ["3.2.9", "Outros ativos tangíveis", "asset", "3"],
            ["3.3.5", "Programas de computador", "asset", "3"],
            ["3.8.2", "Amortizações acumuladas de ativos tangíveis", "asset", "3"],
            ["4.1.1", "Clientes c/c", "asset", "4"],
            ["4.2.1", "Fornecedores c/c", "liability", "4"],
            ["4.3.1", "Empréstimos bancários", "liability", "4"],
            ["4.4.1", "Imposto sobre o rendimento", "liability", "4"],
            ["4.4.2.1", "Imposto retido — rendimentos de trabalho dependente", "liability", "4"],
            ["4.4.3.1", "IVA suportado", "asset", "4"],
            ["4.4.3.3", "IVA liquidado", "liability", "4"],
            ["4.4.3.7", "IVA a pagar", "liability", "4"],
            ["4.4.9", "Contribuições para o INSS", "liability", "4"],
            ["4.6.2.2", "Remunerações a pagar aos trabalhadores", "liability", "4"],
            ["5.1", "Capital", "equity", "5"],
            ["5.9", "Resultados transitados", "equity", "5"],
            ["6.1.1", "Custo das matérias e mercadorias consumidas", "expense", "6"],
            ["6.2.2", "Remunerações dos trabalhadores", "expense", "6"],
            ["6.2.3", "Encargos sobre as remunerações", "expense", "6"],
            ["6.3.1", "Subcontratos", "expense", "6"],
            ["6.3.2.1.1", "Água", "expense", "6"],
            ["6.3.2.1.2", "Eletricidade", "expense", "6"],
            ["6.3.2.1.3", "Combustíveis e lubrificantes", "expense", "6"],
            ["6.3.2.1.6", "Material de escritório", "expense", "6"],
            ["6.3.2.2.2", "Transportes de carga", "expense", "6"],
            ["6.3.2.2.4", "Comunicações", "expense", "6"],
            ["6.3.2.2.5", "Honorários", "expense", "6"],
            ["6.3.2.2.7", "Publicidade e propaganda", "expense", "6"],
            ["6.3.2.2.9", "Deslocações e estadias", "expense", "6"],
            ["6.3.2.3.2", "Rendas e alugueres", "expense", "6"],
            ["6.3.2.3.3", "Seguros", "expense", "6"],
            ["6.3.2.9.9", "Outros fornecimentos e serviços", "expense", "6"],
            ["6.5.1", "Amortizações do período", "expense", "6"],
            ["6.8.2", "Impostos e taxas", "expense", "6"],
            ["6.8.9.6", "Multas e penalidades", "expense", "6"],
            ["6.9.8.1", "Serviços bancários", "expense", "6"],
            ["7.1.1", "Vendas de mercadorias", "revenue", "7"],
            ["7.2", "Prestação de serviços", "revenue", "7"],
            ["7.6.9.9", "Outros rendimentos e ganhos", "revenue", "7"],
            ["8.8", "Resultado líquido do período", "equity", "8"],
        ];

        foreach ($leaves as [$code, $name, $type, $parent]) {
            DB::table("accounts")->insert([
                "code" => $code, "name" => $name, "type" => $type,
                "is_group" => false, "is_active" => true,
                "parent_id" => $ids[$parent] ?? null,
                "created_at" => now(), "updated_at" => now(),
            ]);
        }

        // Expense categories point at their PGC expense accounts
        $ids = DB::table("accounts")->pluck("id", "code");
        $map = [
            "detergent" => "6.1.1",
            "electricity" => "6.3.2.1.2",
            "water" => "6.3.2.1.1",
            "gaji" => "6.2.2",
            "lainnya" => "6.3.2.9.9",
        ];
        foreach ($map as $key => $code) {
            DB::table("expense_categories")
                ->where("key", $key)
                ->update(["account_id" => $ids[$code], "updated_at" => now()]);
        }
    }

    public function down(): void
    {
        DB::table("expense_categories")->update(["account_id" => null]);
        DB::table("journal_lines")->delete();
        DB::table("journal_entries")->delete();
        DB::table("accounts")->delete();

        // Legacy SNC chart (flat codes) restored without journal history
        $this->insertAccounts([
            ["1", "Meios líquidos", "asset"],
            ["2", "Contas correntes", "asset"],
            ["3", "Investimentos", "asset"],
            ["4", "Gastos e perdas", "expense"],
            ["5", "Vendas e prestações de serviços", "revenue"],
            ["6", "Outros gastos e proveitos", "expense"],
            ["7", "Rendas, alvarás e taxas", "expense"],
            ["8", "Resultados", "equity"],
        ], true);

        $ids = DB::table("accounts")->pluck("id", "code");

        $parents = ["11" => "1", "12" => "1", "13" => "1", "21" => "2", "22" => "2", "23" => "2",
            "31" => "3", "32" => "3", "33" => "3", "41" => "4", "42" => "4", "44" => "4", "45" => "4",
            "51" => "5", "52" => "5", "53" => "5", "61" => "6", "71" => "7", "81" => "8", "82" => "8"];
        $legacy = [
            ["11", "Caixa", "asset"], ["12", "Depósitos à ordem", "asset"], ["13", "Outros meios líquidos", "asset"],
            ["21", "Clientes", "asset"], ["22", "Fornecedores", "liability"], ["23", "Adiantamentos e cauções", "liability"],
            ["31", "Investimentos tangíveis", "asset"], ["32", "Investimentos intangíveis", "asset"], ["33", "Investimentos financeiros", "asset"],
            ["41", "Fornecimentos e serviços de terceiros", "expense"], ["42", "Gastos com o pessoal", "expense"],
            ["44", "Gastos e perdas financeiros", "expense"], ["45", "Outros gastos e perdas", "expense"],
            ["51", "Prestação de serviços", "revenue"], ["52", "Venda de mercadorias e pacotes", "revenue"], ["53", "Descontos e abatimentos", "revenue"],
            ["61", "Outros gastos", "expense"], ["71", "Imposto industrial e outras taxas", "expense"],
            ["81", "Resultados transitados", "equity"], ["82", "Resultado líquido do período", "equity"],
        ];

        foreach ($legacy as [$code, $name, $type]) {
            DB::table("accounts")->insert([
                "code" => $code, "name" => $name, "type" => $type,
                "is_group" => false, "is_active" => true,
                "parent_id" => $ids[$parents[$code]] ?? null,
                "created_at" => now(), "updated_at" => now(),
            ]);
        }

        foreach (["gaji" => "42", "detergent" => "41", "electricity" => "41", "water" => "41", "lainnya" => "61"] as $key => $code) {
            DB::table("expense_categories")
                ->where("key", $key)
                ->update(["account_id" => DB::table("accounts")->where("code", $code)->value("id"), "updated_at" => now()]);
        }
    }

    private function insertAccounts(array $rows, bool $isGroup): void
    {
        foreach ($rows as $row) {
            [$code, $name, $type] = $row;
            $parentCode = $row[3] ?? null;

            DB::table("accounts")->insert([
                "code" => $code, "name" => $name, "type" => $type,
                "is_group" => $isGroup, "is_active" => true,
                "parent_id" => $parentCode ? DB::table("accounts")->where("code", $parentCode)->value("id") : null,
                "created_at" => now(), "updated_at" => now(),
            ]);
        }
    }
};
