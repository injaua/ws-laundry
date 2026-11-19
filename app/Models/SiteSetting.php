<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use App\Support\CurrentStore;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * Key / value settings. A row without store is the company-wide value; a row with a store
 * overrides it for that store. Company-level keys (security, localization, WhatsApp channel,
 * mobile-money credentials, public website) are always global; every other key is set per
 * store and falls back to the global value until the store sets its own.
 */
class SiteSetting extends Model
{
    use Loggable;

    protected $fillable = ["key", "value", "store_id"];

    protected $table = "site_settings";

    public const GLOBAL_KEYS = [
        // Localization (Admin → System settings → Localization)
        "locale_default", "currency_code", "currency_symbol", "currency_position", "currency_decimals",
        "number_format", "timezone", "date_format", "time_format", "text_direction", "phone_country_code",
        // WhatsApp channel (one company number / connection)
        "whatsapp_driver", "whatsapp_notifications_driver",
        // Public website
        "hero_title", "hero_subtitle", "hero_cta_text", "hero_cta_url", "about_title", "about_content",
        "about_image", "footer_text", "facebook_url", "instagram_url", "map_embed_url", "whatsapp_number", "address",
    ];

    public const GLOBAL_PREFIXES = ["security_", "mm_"];

    public static function isGlobal(string $key): bool
    {
        if (in_array($key, self::GLOBAL_KEYS, true)) {
            return true;
        }
        foreach (self::GLOBAL_PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** The store a key is read from / written to: null = company-wide. */
    private static function storeFor(string $key): ?int
    {
        return self::isGlobal($key) ? null : CurrentStore::id();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::many([$key]);

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Stored values of several keys: the store's own value where it has one, else the
     * company-wide value. Keys never set are left out.
     *
     * @return array<string, mixed>
     */
    public static function many(array $keys): array
    {
        $storeId = CurrentStore::id();
        $rows = static::whereIn("key", $keys)
            ->where(fn ($q) => $q->whereNull("store_id")->when($storeId, fn ($q) => $q->orWhere("store_id", $storeId)))
            ->get(["key", "value", "store_id"]);

        $values = [];
        foreach ($rows->whereNull("store_id") as $row) {
            $values[$row->key] = $row->value;
        }
        foreach ($rows->whereNotNull("store_id") as $row) {
            if (!self::isGlobal($row->key)) {
                $values[$row->key] = $row->value;
            }
        }

        return $values;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ["store_id" => self::storeFor($key), "key" => $key],
            ["value" => $value]
        );
        SystemSettings::flush();
    }

    /** Every setting as seen from the current store (company values + the store's overrides). */
    public static function allAsArray(): array
    {
        $storeId = CurrentStore::id();
        $values = static::whereNull("store_id")->pluck("value", "key")->toArray();
        if ($storeId) {
            foreach (static::where("store_id", $storeId)->pluck("value", "key") as $key => $value) {
                if (!self::isGlobal($key)) {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }
}
