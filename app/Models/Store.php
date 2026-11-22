<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use App\Support\RequestCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

/** A location of the company. Every business record belongs to exactly one store. */
class Store extends Model
{
    use Loggable;

    protected $fillable = ["name", "code", "address", "phone", "email", "nuit", "is_active"];

    /** Tables whose rows belong to a store (models use the BelongsToStore trait). */
    public const SCOPED_TABLES = [
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

    protected function casts(): array
    {
        return ["is_active" => "boolean"];
    }

    /** The first store: owner of records created where no store applies (seeders, tests, public pages). */
    public static function defaultId(): ?int
    {
        // Kept for the request / job only (RequestCache), and only once a store exists.
        $cache = RequestCache::instance();
        if (!$cache->has("store:default-id")) {
            try {
                $id = static::query()->orderBy("id")->value("id");
            } catch (QueryException) {
                return null; // earlier migrations seed data before the stores table exists
            }
            if ($id === null) {
                return null;
            }
            $cache->put("store:default-id", (int) $id);
        }

        return $cache->get("store:default-id");
    }

    public static function forgetDefault(): void
    {
        RequestCache::instance()->forget("store:default-id");
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where("is_active", true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owners(): HasMany
    {
        return $this->users()->where("role", "owner");
    }

    public function label(): string
    {
        return "{$this->code} · {$this->name}";
    }
}
