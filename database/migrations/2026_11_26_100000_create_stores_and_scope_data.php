<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-store: every business record belongs to one store (location). The data that
 * already exists becomes the "main store". Users other than admins belong to a store;
 * settings can be global (company) or per store; numbered series are unique per store.
 */
return new class extends Migration
{
    /** Business tables that get a store_id (models use the BelongsToStore trait). */
    public const TABLES = [
        "absence_justifications", "accounts", "accounting_periods", "attendances", "bank_deposits",
        "cash_movements", "cash_sessions", "cheques", "complaints", "conversations", "courier_jobs",
        "credit_notes", "customers", "customer_message_logs", "customer_prices", "delivery_zones",
        "employees", "employee_salaries", "equipments", "equipment_recipes", "expenses",
        "expense_categories", "inventory_items", "inventory_lots", "inventory_movements", "invoices",
        "journal_entries", "leaves", "loyalty_points", "machine_loads", "maintenance_jobs",
        "maintenance_job_items", "message_templates", "mobile_transactions", "orders", "order_photos",
        "order_pieces", "packages", "package_recipes", "payments", "promotions", "purchase_orders",
        "services", "service_recipes", "suppliers", "trial_balance_archives", "vacations",
        "wallet_statements", "wallet_statement_lines", "whatsapp_messages",
    ];

    /** Unique columns that become unique per store: table => [columns]. */
    private const PER_STORE_UNIQUE = [
        "customers" => ["phone"],
        "orders" => ["order_number"],
        "accounts" => ["code"],
        "expense_categories" => ["key"],
        "equipments" => ["code"],
        "accounting_periods" => ["year", "month"],
        "order_pieces" => ["code"],
        "credit_notes" => ["number"],
        "complaints" => ["number"],
        "invoices" => ["number"],
        "machine_loads" => ["code"],
    ];

    public function up(): void
    {
        Schema::create("stores", function (Blueprint $table) {
            $table->id();
            $table->string("name", 120);
            $table->string("code", 10)->unique();
            $table->string("address")->nullable();
            $table->string("phone", 30)->nullable();
            $table->string("email")->nullable();
            $table->string("nuit", 20)->nullable();
            $table->boolean("is_active")->default(true);
            $table->timestamps();
        });

        $name = DB::table("site_settings")->where("key", "company_name")->value("value") ?: "Loja principal";
        $storeId = DB::table("stores")->insertGetId([
            "name" => $name,
            "code" => "LJ01",
            "address" => DB::table("site_settings")->where("key", "company_address")->value("value"),
            "phone" => DB::table("site_settings")->where("key", "company_phone")->value("value"),
            "nuit" => DB::table("site_settings")->where("key", "company_nuit")->value("value"),
            "is_active" => true,
            "created_at" => now(),
            "updated_at" => now(),
        ]);

        Schema::table("users", function (Blueprint $table) {
            $table->foreignId("store_id")->nullable()->after("role")->constrained()->restrictOnDelete();
        });
        DB::table("users")->where("role", "!=", "admin")->update(["store_id" => $storeId]);

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId("store_id")->nullable()->constrained()->restrictOnDelete();
            });
            DB::table($name)->update(["store_id" => $storeId]);
        }

        foreach (self::PER_STORE_UNIQUE as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                $table->dropUnique($columns);
                $table->unique(["store_id", ...$columns]);
            });
        }

        // Settings: no store = company-wide value (and the default for every store).
        Schema::table("site_settings", function (Blueprint $table) {
            $table->dropUnique(["key"]);
            $table->foreignId("store_id")->nullable()->after("id")->constrained()->cascadeOnDelete();
            $table->unique(["store_id", "key"]);
        });

        // Audit trail: which store an action happened in (null = company level).
        Schema::table("activity_logs", function (Blueprint $table) {
            $table->foreignId("store_id")->nullable()->index();
        });
        DB::table("activity_logs")->update(["store_id" => $storeId]);
    }

    public function down(): void
    {
        Schema::table("activity_logs", function (Blueprint $table) {
            $table->dropIndex(["store_id"]);
            $table->dropColumn("store_id");
        });

        Schema::table("site_settings", function (Blueprint $table) {
            $table->dropUnique(["store_id", "key"]);
            $table->dropConstrainedForeignId("store_id");
            $table->unique(["key"]);
        });

        foreach (self::PER_STORE_UNIQUE as $name => $columns) {
            Schema::table($name, function (Blueprint $table) use ($columns) {
                $table->dropUnique(["store_id", ...$columns]);
                $table->unique($columns);
            });
        }

        foreach (self::TABLES as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId("store_id"));
        }

        Schema::table("users", fn (Blueprint $table) => $table->dropConstrainedForeignId("store_id"));
        Schema::dropIfExists("stores");
    }
};
