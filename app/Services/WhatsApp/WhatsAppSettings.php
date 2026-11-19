<?php

namespace App\Services\WhatsApp;

use App\Models\SiteSetting;
use App\Support\CurrentStore;
use App\Support\StoreSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp channels chosen in Admin → WhatsApp settings. The .env values are only the defaults:
 *  - whatsapp_driver: conversations through log, cloud (official API) or web (a normal number linked by QR code);
 *  - whatsapp_notifications_driver: automatic notifications through cloud or log, "off", or "" (= same as conversations);
 *  - whatsapp_cloud_*: the official API number and credentials (empty = the .env values).
 *
 * The company configuration applies to every store, unless a store has its own (StoreSettings
 * "whatsapp"): then it has its own channels, its own official API number and its own QR-code session
 * (its own linked phone), and the company values are not used for it at all.
 *
 * The QR service (whatsapp-web/) and Laravel share a secret. When WHATSAPP_WEB_TOKEN is not in the .env,
 * one is generated in storage/app/whatsapp-web.token, which the service also reads: no file to edit by hand.
 */
class WhatsAppSettings
{
    public const DRIVERS = ["log", "cloud", "web"];

    public const NOTIFICATION_DRIVERS = ["", "cloud", "log", "off"];

    /** Official API fields: setting key suffix => secret (stored encrypted). */
    public const CLOUD_FIELDS = [
        "phone_number_id" => false,
        "access_token" => true,
        "app_secret" => true,
        "verify_token" => false,
    ];

    public const KEYS = [
        "whatsapp_driver", "whatsapp_notifications_driver",
        "whatsapp_cloud_phone_number_id", "whatsapp_cloud_access_token", "whatsapp_cloud_app_secret", "whatsapp_cloud_verify_token",
    ];

    /** QR-code session of the company number. */
    public const COMPANY_SESSION = "default";

    public static function tokenPath(): string
    {
        return storage_path("app/whatsapp-web.token");
    }

    /** QR-code session of a level (null = company). */
    public static function session(?int $level): string
    {
        return $level === null ? self::COMPANY_SESSION : "store-{$level}";
    }

    /** Store of a QR-code session name; null for the company session or an unknown name. */
    public static function storeOfSession(string $session): ?int
    {
        return preg_match('/^store-(\d+)$/', $session, $m) ? (int) $m[1] : null;
    }

    /** Called on boot: the company's stored choices override config/whatsapp.php. */
    public static function apply(): void
    {
        try {
            $stored = SiteSetting::valuesAt(null, self::KEYS);
        } catch (Throwable) {
            $stored = []; // table not migrated yet
        }

        if (in_array($stored["whatsapp_driver"] ?? null, self::DRIVERS, true)) {
            config(["whatsapp.driver" => $stored["whatsapp_driver"]]);
        }
        if (array_key_exists("whatsapp_notifications_driver", $stored) && in_array((string) $stored["whatsapp_notifications_driver"], self::NOTIFICATION_DRIVERS, true)) {
            config(["whatsapp.notifications_driver" => (string) $stored["whatsapp_notifications_driver"] ?: null]);
        }
        foreach (self::cloudValues($stored) as $field => $value) {
            if (filled($value)) {
                config(["whatsapp.cloud.{$field}" => $value]);
            }
        }

        if (strlen((string) config("whatsapp.web.token")) < 16 && is_file(self::tokenPath())) {
            config(["whatsapp.web.token" => trim((string) file_get_contents(self::tokenPath()))]);
        }
    }

    /**
     * The WhatsApp configuration a store works with (null = company): the shape of
     * config/whatsapp.php, plus "level" (null = company configuration, else the store id)
     * and web.session (its QR-code session).
     */
    public static function for(?int $storeId): array
    {
        return self::forLevel(StoreSettings::levelFor("whatsapp", $storeId));
    }

    /** The configuration of the store worked in now. */
    public static function current(): array
    {
        return self::for(CurrentStore::id());
    }

    /** The configuration stored at one level (null = company, as applied to config on boot). */
    public static function forLevel(?int $level): array
    {
        $config = (array) config("whatsapp");
        $config["level"] = $level;
        $config["web"]["session"] = self::session($level);

        if ($level === null) {
            return $config;
        }

        $stored = StoreSettings::values("whatsapp", $level);
        $config["driver"] = in_array($stored["whatsapp_driver"] ?? null, self::DRIVERS, true) ? $stored["whatsapp_driver"] : "log";
        $notifications = (string) ($stored["whatsapp_notifications_driver"] ?? "");
        $config["notifications_driver"] = in_array($notifications, self::NOTIFICATION_DRIVERS, true) && $notifications !== "" ? $notifications : null;
        // A store's own number: nothing comes from the company or the .env.
        $config["cloud"] = array_merge($config["cloud"] ?? [], self::cloudValues($stored));

        return $config;
    }

    /** @return array<string, ?string> official API fields of stored values, secrets decrypted */
    private static function cloudValues(array $stored): array
    {
        $values = [];
        foreach (self::CLOUD_FIELDS as $field => $secret) {
            $value = $stored["whatsapp_cloud_{$field}"] ?? null;
            if ($secret && filled($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    $value = null;
                }
            }
            $values[$field] = filled($value) ? (string) $value : null;
        }

        return $values;
    }

    /** A secret of the official API is stored at this level (for "leave blank to keep"). */
    public static function hasSecret(string $field, ?int $level): bool
    {
        return filled(SiteSetting::valuesAt($level, ["whatsapp_cloud_{$field}"])["whatsapp_cloud_{$field}"] ?? null);
    }

    /**
     * Saves the channels (and, when given, the official API fields) at a level: null = company,
     * a store id = that store, which then has its own WhatsApp configuration. An empty secret keeps
     * the stored one.
     */
    public static function save(string $driver, string $notificationsDriver, ?int $level = null, ?array $cloud = null): void
    {
        if ($level !== null) {
            StoreSettings::takeOver("whatsapp", $level);
        }

        StoreSettings::set("whatsapp", $level, "whatsapp_driver", $driver);
        StoreSettings::set("whatsapp", $level, "whatsapp_notifications_driver", $notificationsDriver);

        foreach ($cloud === null ? [] : self::CLOUD_FIELDS as $field => $secret) {
            $value = trim((string) ($cloud[$field] ?? ""));
            if ($secret) {
                if ($value === "") {
                    continue;
                }
                $value = Crypt::encryptString($value);
            }
            StoreSettings::set("whatsapp", $level, "whatsapp_cloud_{$field}", $value);
        }

        if ($driver === "web") {
            self::ensureWebToken();
        }

        self::apply();
    }

    /** @return array<int, array> the company configuration (key "") and every store with its own */
    public static function allLevels(): array
    {
        $levels = ["" => self::forLevel(null)];
        foreach (StoreSettings::owners("whatsapp") as $storeId) {
            $levels[$storeId] = self::forLevel($storeId);
        }

        return $levels;
    }

    /** The QR-code service is needed: the company or a store chats through a number linked by QR. */
    public static function anyUsesWeb(): bool
    {
        return collect(self::allLevels())->contains(fn (array $config) => $config["driver"] === "web");
    }

    /** The shared secret with the QR service; generated once when the .env has none. */
    public static function ensureWebToken(): string
    {
        $token = (string) config("whatsapp.web.token");

        if (strlen($token) >= 16) {
            return $token;
        }

        $token = Str::random(48);
        File::ensureDirectoryExists(dirname(self::tokenPath()));
        file_put_contents(self::tokenPath(), $token);
        @chmod(self::tokenPath(), 0600);
        config(["whatsapp.web.token" => $token]);

        return $token;
    }
}
