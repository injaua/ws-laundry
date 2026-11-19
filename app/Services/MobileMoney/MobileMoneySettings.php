<?php

namespace App\Services\MobileMoney;

use App\Models\SiteSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-operator settings. Mode: off (button hidden), simulation (no real money,
 * for training), sandbox (operator test environment) or live.
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

    public static function mode(string $provider): string
    {
        $mode = (string) SiteSetting::get("mm_{$provider}_mode", "off");

        return in_array($mode, self::MODES, true) ? $mode : "off";
    }

    public static function enabled(string $provider): bool
    {
        return self::mode($provider) !== "off";
    }

    /** @return string[] operators that can receive push requests now */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(array_keys(self::FIELDS), fn ($p) => self::enabled($p)));
    }

    public static function get(string $provider): array
    {
        $settings = ["environment" => self::mode($provider) === "live" ? "live" : "sandbox"];

        foreach (self::FIELDS[$provider] ?? [] as $field => $secret) {
            $value = SiteSetting::get("mm_{$provider}_{$field}");
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

    /** Saves the mode and fields; an empty secret keeps the one already stored. */
    public static function save(string $provider, array $data): void
    {
        SiteSetting::set("mm_{$provider}_mode", in_array($data["mode"] ?? "off", self::MODES, true) ? $data["mode"] : "off");

        foreach (self::FIELDS[$provider] as $field => $secret) {
            $value = $data[$field] ?? null;
            if ($secret) {
                if (blank($value)) {
                    continue;
                }
                $value = Crypt::encryptString(trim($value));
            }
            SiteSetting::set("mm_{$provider}_{$field}", $value === null ? "" : trim((string) $value));
        }
    }

    public static function hasSecret(string $provider, string $field): bool
    {
        return filled(SiteSetting::get("mm_{$provider}_{$field}"));
    }

    /** Credentials needed for sandbox/live are present. */
    public static function configured(string $provider): bool
    {
        $s = self::get($provider);

        return match ($provider) {
            "mpesa" => filled($s["api_key"]) && filled($s["public_key"]) && filled($s["service_provider_code"]),
            "emola" => filled($s["partner_code"]) && filled($s[$s["environment"] === "live" ? "live_url" : "sandbox_url"]),
            default => false,
        };
    }

    public static function gateway(string $provider): ?MobileMoneyGateway
    {
        return match (self::mode($provider)) {
            "simulation" => new SimulatedGateway($provider),
            "sandbox", "live" => match ($provider) {
                "mpesa" => new MpesaGateway(self::get("mpesa")),
                "emola" => new EmolaGateway(self::get("emola")),
                default => null,
            },
            default => null,
        };
    }
}
