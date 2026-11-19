<?php

namespace App\Services\WhatsApp;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * WhatsApp channels chosen in Admin → WhatsApp settings. The .env values are only the defaults:
 *  - whatsapp_driver: conversations through log, cloud (official API) or web (a normal number linked by QR code);
 *  - whatsapp_notifications_driver: automatic notifications through cloud or log, "off", or "" (= same as conversations).
 *
 * The QR service (whatsapp-web/) and Laravel share a secret. When WHATSAPP_WEB_TOKEN is not in the .env,
 * one is generated in storage/app/whatsapp-web.token, which the service also reads: no file to edit by hand.
 */
class WhatsAppSettings
{
    public const DRIVERS = ["log", "cloud", "web"];

    public const NOTIFICATION_DRIVERS = ["", "cloud", "log", "off"];

    public static function tokenPath(): string
    {
        return storage_path("app/whatsapp-web.token");
    }

    /** Called on boot: stored choices override config/whatsapp.php. */
    public static function apply(): void
    {
        try {
            $stored = SiteSetting::many(["whatsapp_driver", "whatsapp_notifications_driver"]);
        } catch (Throwable) {
            $stored = []; // table not migrated yet
        }

        if (in_array($stored["whatsapp_driver"] ?? null, self::DRIVERS, true)) {
            config(["whatsapp.driver" => $stored["whatsapp_driver"]]);
        }
        if (array_key_exists("whatsapp_notifications_driver", $stored) && in_array((string) $stored["whatsapp_notifications_driver"], self::NOTIFICATION_DRIVERS, true)) {
            config(["whatsapp.notifications_driver" => (string) $stored["whatsapp_notifications_driver"] ?: null]);
        }

        if (strlen((string) config("whatsapp.web.token")) < 16 && is_file(self::tokenPath())) {
            config(["whatsapp.web.token" => trim((string) file_get_contents(self::tokenPath()))]);
        }
    }

    public static function save(string $driver, string $notificationsDriver): void
    {
        SiteSetting::set("whatsapp_driver", $driver);
        SiteSetting::set("whatsapp_notifications_driver", $notificationsDriver);

        if ($driver === "web") {
            self::ensureWebToken();
        }

        self::apply();
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
