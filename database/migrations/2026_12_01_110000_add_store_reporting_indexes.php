<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the per-store queries of the dashboard, reports, reconciliation and lists
 * (load test: 100 stores, 200k orders). Every store-scoped query filters on store_id, so
 * the indexes start with it:
 *
 *  - payments (store_id, paid_at): collections by day / period;
 *  - payments (store_id, method, reconciled_at): mobile-wallet reconciliation;
 *  - payments (store_id, bounced_at) REPLACES the bounced_at index: almost every row is
 *    NULL there, and MySQL merged it with the store index for every "bounced_at is null"
 *    query, reading the payments of all stores (≈50 ms → ≈1 ms per query);
 *  - orders (store_id, received_at | status | created_at | ready_at): periods, production
 *    queues, latest-first lists (no more scan + filesort of the store's whole history);
 *  - expenses (store_id, expense_date): expenses by period.
 *
 * MySQL drops the implicit index of a foreign key once another index starts with the same
 * column, so down() puts a plain store_id index back before removing the composite ones.
 */
return new class extends Migration
{
    private const INDEXES = [
        "payments" => [
            "payments_store_paid_at_index" => ["store_id", "paid_at"],
            "payments_store_method_reconciled_index" => ["store_id", "method", "reconciled_at"],
            "payments_store_bounced_at_index" => ["store_id", "bounced_at"],
        ],
        "orders" => [
            "orders_store_received_at_index" => ["store_id", "received_at"],
            "orders_store_status_index" => ["store_id", "status"],
            "orders_store_created_at_index" => ["store_id", "created_at"],
            "orders_store_ready_at_index" => ["store_id", "ready_at"],
        ],
        "expenses" => [
            "expenses_store_expense_date_index" => ["store_id", "expense_date"],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $indexes) {
            Schema::table($name, function (Blueprint $table) use ($indexes) {
                foreach ($indexes as $index => $columns) {
                    $table->index($columns, $index);
                }
            });
        }

        Schema::table("payments", function (Blueprint $table) {
            $table->dropIndex(["bounced_at"]);
        });
    }

    public function down(): void
    {
        Schema::table("payments", function (Blueprint $table) {
            $table->index("bounced_at");
        });

        foreach (self::INDEXES as $name => $indexes) {
            // The store_id foreign key needs an index of its own once the composite ones go
            // (orders keeps the (store_id, order_number) unique index for it).
            if ($name !== "orders" && !Schema::hasIndex($name, "{$name}_store_id_foreign")) {
                Schema::table($name, fn (Blueprint $table) => $table->index("store_id", "{$name}_store_id_foreign"));
            }
            Schema::table($name, function (Blueprint $table) use ($indexes) {
                foreach (array_keys($indexes) as $index) {
                    $table->dropIndex($index);
                }
            });
        }
    }
};
