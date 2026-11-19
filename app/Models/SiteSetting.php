<?php

namespace App\Models;

use App\Models\Traits\Loggable;
use App\Support\CurrentStore;
use App\Support\SettingsCache;
use App\Support\SystemSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * Key / value settings. A row without store is the company-wide value; a row with a store
 * overrides it for that store. Company-level keys (security, localization, public website,
 * WhatsApp channel, payment methods and mobile-money credentials) are read and written at
 * company level by get() / set(); every other key is set per store and falls back to the
 * global value until the store sets its own.
 *
 * WhatsApp and payments can still be configured per store, as a whole group: see
 * App\Support\StoreSettings, which reads and writes them with valuesAt() / setAt().
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
        // WhatsApp channel (company default; a store may have its own: StoreSettings "whatsapp")
        "whatsapp_driver", "whatsapp_notifications_driver",
        // Accepted payment methods (company default; a store may have its own: StoreSettings "payments")
        "payment_methods",
        // Public website
        "hero_title", "hero_subtitle", "hero_cta_text", "hero_cta_url", "about_title", "about_content",
        "about_image", "footer_text", "facebook_url", "instagram_url", "map_embed_url", "whatsapp_number", "address",
    ];

    public const GLOBAL_PREFIXES = ["security_", "mm_", "whatsapp_cloud_"];

    protected static function booted(): void
    {
        // Settings are read once per request (SettingsCache): any change empties it.
        static::saved(fn () => self::flushCaches());
        static::deleted(fn () => self::flushCaches());
    }

    private static function flushCaches(): void
    {
        SettingsCache::instance()->flush();
        SystemSettings::flush();
    }

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
        $cache = SettingsCache::instance();
        $wanted = array_flip($keys);
        $values = array_intersect_key($cache->level(null), $wanted);

        $storeId = CurrentStore::id();
        if ($storeId) {
            foreach (array_intersect_key($cache->level($storeId), $wanted) as $key => $value) {
                if (!self::isGlobal($key)) {
                    $values[$key] = $value;
                }
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
        self::flushCaches();
    }

    /**
     * Values stored at exactly one level (null = company), without any fallback.
     *
     * @return array<string, mixed>
     */
    public static function valuesAt(?int $storeId, array $keys): array
    {
        return array_intersect_key(SettingsCache::instance()->level($storeId), array_flip($keys));
    }

    /** Writes a value at exactly one level (null = company), whatever the key. */
    public static function setAt(?int $storeId, string $key, mixed $value): void
    {
        static::updateOrCreate(["store_id" => $storeId, "key" => $key], ["value" => $value]);
        self::flushCaches();
    }

    /** Removes a store's own values of these keys (it falls back to the company values again). */
    public static function forgetAt(int $storeId, array $keys): void
    {
        static::where("store_id", $storeId)->whereIn("key", $keys)->get()->each->delete();
        self::flushCaches();
    }

    /** Every setting as seen from the current store (company values + the store's overrides). */
    public static function allAsArray(): array
    {
        $storeId = CurrentStore::id();
        $values = SettingsCache::instance()->level(null);
        if ($storeId) {
            foreach (SettingsCache::instance()->level($storeId) as $key => $value) {
                if (!self::isGlobal($key)) {
                    $values[$key] = $value;
                }
            }
        }

        return $values;
    }
}
