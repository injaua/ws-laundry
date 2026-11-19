<?php

namespace App\Services\MobileMoney;

use App\Services\Payments\PaymentMethods;
use App\Support\StoreSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-operator settings. Mode: off (button hidden), simulation (no real money,
 * for training), sandbox (operator test environment) or live.
 *
 * Company-wide by default; a store with its own payment configuration (StoreSettings
 * "payments") has its own operators, credentials and modes. Reads use the store worked in;
 * every method also takes an explicit level (null = company, a store id = that store's own values).
 */
class MobileMoneySettings
{
    public const MODES = ["off", "simulation", "sandbox", "live"];

    /** Fields per operator; secrets are stored encrypted. */
    public const FIELDS = [
        "mpesa" => [
            "api_key" => true,
            "public_key" => true,
            "service_provider_code" => false,
            "origin" => false,
        ],
        "emola" => [
            "partner_code" => false,
            "username" => false,
            "password" => true,
            "api_key" => true,
            "sandbox_url" => false,
            "live_url" => false,
        ],
    ];

    /** @return string[] every stored key (part of the "payments" settings group) */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::FIELDS as $provider => $fields) {
            $keys[] = "mm_{$provider}_mode";
            foreach (array_keys($fields) as $field) {
                $keys[] = "mm_{$provider}_{$field}";
            }
        }

        return $keys;
    }

    /** "current" = the level used by the store worked in now. */
    private static function level(int|string|null $level): ?int
    {
        return $level === "current" ? StoreSettings::currentLevel("payments") : $level;
    }

    private static function stored(int|string|null $level): array
    {
        return StoreSettings::values("payments", self::level($level));
    }

    public static function mode(string $provider, int|string|null $level = "current"): string
    {
        $mode = (string) (self::stored($level)["mm_{$provider}_mode"] ?? "off");

        return in_array($mode, self::MODES, true) ? $mode : "off";
    }

    /** Push requests possible: the operator is on and its method is accepted. */
    public static function enabled(string $provider, int|string|null $level = "current"): bool
    {
        return self::mode($provider, $level) !== "off" && in_array($provider, PaymentMethods::enabled($level), true);
    }

    /** @return string[] operators that can receive push requests now */
    public static function enabledProviders(int|string|null $level = "current"): array
    {
        return array_values(array_filter(array_keys(self::FIELDS), fn ($p) => self::enabled($p, $level)));
    }

    /** Some store (or the company) takes mobile payments: the public "pay" page is offered. */
    public static function enabledAnywhere(): bool
    {
        foreach ([null, ...StoreSettings::owners("payments")] as $level) {
            if (self::enabledProviders($level) !== []) {
                return true;
            }
        }

        return false;
    }

    public static function get(string $provider, int|string|null $level = "current"): array
    {
        $stored = self::stored($level);
        $settings = ["environment" => self::mode($provider, $level) === "live" ? "live" : "sandbox"];

        foreach (self::FIELDS[$provider] ?? [] as $field => $secret) {
            $value = $stored["mm_{$provider}_{$field}"] ?? null;
            if ($secret && filled($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    $value = null;
                }
            }
            $settings[$field] = $value;
        }

        return $settings;
    }

    /**
     * Saves the mode and fields at a level (null = company, a store id = that store, which then
     * has its own payment configuration); an empty secret keeps the one already stored.
     */
    public static function save(string $provider, array $data, int|string|null $level = null): void
    {
        $level = $level === "current" ? StoreSettings::currentLevel("payments") : $level;
        if ($level !== null) {
            StoreSettings::takeOver("payments", $level);
        }

        StoreSettings::set("payments", $level, "mm_{$provider}_mode", in_array($data["mode"] ?? "off", self::MODES, true) ? $data["mode"] : "off");

        foreach (self::FIELDS[$provider] as $field => $secret) {
            $value = $data[$field] ?? null;
            if ($secret) {
                if (blank($value)) {
                    continue;
                }
                $value = Crypt::encryptString(trim($value));
            }
            StoreSettings::set("payments", $level, "mm_{$provider}_{$field}", $value === null ? "" : trim((string) $value));
        }
    }

    public static function hasSecret(string $provider, string $field, int|string|null $level = "current"): bool
    {
        return filled(self::stored($level)["mm_{$provider}_{$field}"] ?? null);
    }

    /** Credentials needed for sandbox/live are present. */
    public static function configured(string $provider, int|string|null $level = "current"): bool
    {
        $s = self::get($provider, $level);

        return match ($provider) {
            "mpesa" => filled($s["api_key"]) && filled($s["public_key"]) && filled($s["service_provider_code"]),
            "emola" => filled($s["partner_code"]) && filled($s[$s["environment"] === "live" ? "live_url" : "sandbox_url"]),
            default => false,
        };
    }

    public static function gateway(string $provider, int|string|null $level = "current"): ?MobileMoneyGateway
    {
        return match (self::mode($provider, $level)) {
            "simulation" => new SimulatedGateway($provider),
            "sandbox", "live" => match ($provider) {
                "mpesa" => new MpesaGateway(self::get("mpesa", $level)),
                "emola" => new EmolaGateway(self::get("emola", $level)),
                default => null,
            },
            default => null,
        };
    }
}
