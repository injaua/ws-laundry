<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monetary precision: every amount column becomes DECIMAL(12, 2).
 * Orders, payments, tills, invoices and credit notes already kept cents, but
 * expenses, the journal, payroll, stock costs, purchases, packages and credit
 * limits stored whole units, so a 150,50 payment was posted to the ledger as
 * 151 and the books stopped matching the sales. Existing values are kept.
 */
return new class extends Migration
{
    /** table => [column => "nullable" | "default" | "required"] */
    private const COLUMNS = [
        "expenses" => ["amount" => "required"],
        "packages" => ["price" => "required"],
        "journal_lines" => ["debit" => "default", "credit" => "default"],
        "employees" => ["base_salary" => "default"],
        "employee_salaries" => [
            "base_salary" => "default",
            "bonus" => "default",
            "deduction" => "default",
            "absence_deduction" => "default",
            "net_salary" => "default",
        ],
        "expense_categories" => ["default_amount" => "default"],
        "equipments" => ["purchase_price" => "nullable"],
        "inventory_items" => ["unit_cost" => "default"],
        "inventory_movements" => ["unit_cost" => "nullable"],
        "inventory_lots" => ["unit_cost" => "nullable"],
        "maintenance_jobs" => ["cost" => "default"],
        "purchase_orders" => ["total" => "default"],
        "purchase_order_items" => ["unit_cost" => "required", "subtotal" => "required"],
        "trial_balance_archives" => ["total_debit" => "default", "total_credit" => "default"],
        "trial_balance_archive_lines" => [
            "total_debit" => "default",
            "total_credit" => "default",
            "balance_debit" => "default",
            "balance_credit" => "default",
        ],
        "customers" => ["credit_limit" => "default"],
    ];

    public function up(): void
    {
        $this->resize(2);
    }

    /** Rolling back rounds every amount to whole units: cents are lost. */
    public function down(): void
    {
        $this->resize(0);
    }

    private function resize(int $scale): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $scale) {
                foreach ($columns as $column => $kind) {
                    $definition = $blueprint->decimal($column, 12, $scale);
                    match ($kind) {
                        "nullable" => $definition->nullable(),
                        "default" => $definition->default(0),
                        default => null,
                    };
                    $definition->change();
                }
            });
        }
    }
};
