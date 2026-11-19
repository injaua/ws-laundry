<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the remaining Portuguese/Indonesian identifiers stored in the
 * database to English: trial balance archive tables, IVA → VAT columns,
 * the VAT rate setting and the seeded expense category keys.
 */
return new class extends Migration
{
    private const CATEGORY_KEYS = [
        "gaji" => "salary",
        "lainnya" => "other",
        "produtos" => "cleaning_products",
        "manutencao" => "maintenance",
    ];

    private const MODELS = [
        "App\\Models\\BalanceteArchiveLine" => "App\\Models\\TrialBalanceArchiveLine",
        "App\\Models\\BalanceteArchive" => "App\\Models\\TrialBalanceArchive",
    ];

    public function up(): void
    {
        $this->renameArchiveTables(
            ["balancete_archives", "balancete_archive_lines", "balancete_archive_id"],
            ["trial_balance_archives", "trial_balance_archive_lines", "trial_balance_archive_id"]
        );

        foreach (["orders", "credit_notes"] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn("iva_rate", "vat_rate");
                $table->renameColumn("iva_amount", "vat_amount");
            });
        }

        DB::table("site_settings")->where("key", "iva_rate")->update(["key" => "vat_rate"]);
        $this->renameCategoryKeys(self::CATEGORY_KEYS);
        $this->renameLoggedModels(self::MODELS);
    }

    public function down(): void
    {
        $this->renameLoggedModels(array_flip(self::MODELS));
        $this->renameCategoryKeys(array_flip(self::CATEGORY_KEYS));
        DB::table("site_settings")->where("key", "vat_rate")->update(["key" => "iva_rate"]);

        foreach (["orders", "credit_notes"] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->renameColumn("vat_rate", "iva_rate");
                $table->renameColumn("vat_amount", "iva_amount");
            });
        }

        $this->renameArchiveTables(
            ["trial_balance_archives", "trial_balance_archive_lines", "trial_balance_archive_id"],
            ["balancete_archives", "balancete_archive_lines", "balancete_archive_id"]
        );
    }

    private function renameArchiveTables(array $from, array $to): void
    {
        [$archives, $lines, $foreignKey] = $from;
        [$newArchives, $newLines, $newForeignKey] = $to;

        Schema::table($lines, function (Blueprint $table) use ($lines, $foreignKey) {
            $table->dropIndex("{$lines}_{$foreignKey}_account_code_index");
        });

        Schema::rename($archives, $newArchives);
        Schema::rename($lines, $newLines);

        Schema::table($newLines, function (Blueprint $table) use ($foreignKey, $newForeignKey) {
            $table->renameColumn($foreignKey, $newForeignKey);
        });

        Schema::table($newLines, function (Blueprint $table) use ($newForeignKey) {
            $table->index([$newForeignKey, "account_code"]);
        });
    }

    private function renameCategoryKeys(array $map): void
    {
        foreach ($map as $old => $new) {
            DB::table("expense_categories")->where("key", $old)->update(["key" => $new]);
            DB::table("expenses")->where("category", $old)->update(["category" => $new]);
        }
    }

    private function renameLoggedModels(array $map): void
    {
        foreach ($map as $old => $new) {
            DB::table("activity_logs")->where("subject_type", $old)->update(["subject_type" => $new]);

            $oldBase = class_basename($old);
            $newBase = class_basename($new);
            DB::table("activity_logs")
                ->where("description", "like", $oldBase . " %")
                ->update(["description" => DB::raw("REPLACE(description, '{$oldBase} ', '{$newBase} ')")]);
        }
    }
};
