<?php

namespace App\Support;

use App\Models\MobileTransaction;
use App\Models\WhatsAppMessage;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\Payments\PaymentMethods;
use App\Services\WhatsApp\WhatsAppChannels;
use App\Services\WhatsApp\WhatsAppSettings;

/**
 * Whether a store is ready to take payments and talk to its customers: the configuration it
 * really uses (its own, or the company's), what is missing, and the recent failures.
 *
 * Each check gives a state ("ok", "warning" or "problem") and the translation keys of the
 * issues found (app.readiness_*), shown on the dashboard and in Admin → Stores.
 */
final class StoreReadiness
{
    public const FAILURE_DAYS = 7;

    /**
     * @return array{state: string, payments: array, whatsapp: array}
     */
    public static function for(?int $storeId): array
    {
        $compute = fn () => self::compute($storeId);
        $result = $storeId && $storeId !== CurrentStore::id() ? CurrentStore::run($storeId, $compute) : $compute();

        $result["state"] = self::worst([$result["payments"]["state"], $result["whatsapp"]["state"]]);

        return $result;
    }

    private static function compute(?int $storeId): array
    {
        return ["payments" => self::payments($storeId), "whatsapp" => self::whatsapp($storeId)];
    }

    private static function payments(?int $storeId): array
    {
        $level = StoreSettings::levelFor("payments", $storeId);
        $methods = PaymentMethods::enabled($level);
        $issues = [];
        $states = ["ok"];

        $providers = [];
        foreach (MobileTransaction::PROVIDERS as $provider) {
            $mode = MobileMoneySettings::mode($provider, $level);
            $accepted = in_array($provider, $methods, true);
            $configured = MobileMoneySettings::configured($provider, $level);
            $providers[$provider] = ["mode" => $mode, "accepted" => $accepted, "configured" => $configured];

            if (in_array($mode, ["sandbox", "live"], true) && !$configured) {
                $issues[] = ["readiness_mm_incomplete", ["provider" => __("app.$provider")]];
                $states[] = "problem";
            } elseif ($mode === "simulation") {
                $issues[] = ["readiness_mm_simulation", ["provider" => __("app.$provider")]];
                $states[] = "warning";
            } elseif ($mode === "sandbox") {
                $issues[] = ["readiness_mm_sandbox", ["provider" => __("app.$provider")]];
                $states[] = "warning";
            }
            if ($mode !== "off" && !$accepted) {
                $issues[] = ["readiness_mm_not_accepted", ["provider" => __("app.$provider")]];
                $states[] = "warning";
            }
        }

        $failed = MobileTransaction::where("status", "failed")->where("created_at", ">=", now()->subDays(self::FAILURE_DAYS))->count();
        if ($failed > 0) {
            $issues[] = ["readiness_mm_failures", ["count" => $failed, "days" => self::FAILURE_DAYS]];
            $states[] = "warning";
        }

        return [
            "state" => self::worst($states),
            "own" => $level !== null,
            "methods" => $methods,
            "providers" => $providers,
            "failed" => $failed,
            "issues" => $issues,
        ];
    }

    private static function whatsapp(?int $storeId): array
    {
        $level = StoreSettings::levelFor("whatsapp", $storeId);
        $config = WhatsAppSettings::forLevel($level);
        $conversation = $config["driver"];
        $notificationsDriver = ($config["notifications_driver"] ?? null) ?: $conversation;
        $notifications = in_array($notificationsDriver, WhatsAppChannels::NOTIFICATION_DRIVERS, true) ? $notificationsDriver : null;
        $cloudReady = filled($config["cloud"]["phone_number_id"] ?? null) && filled($config["cloud"]["access_token"] ?? null);
        $issues = [];
        $states = ["ok"];

        if (in_array("cloud", [$conversation, $notifications], true) && !$cloudReady) {
            $issues[] = ["readiness_wa_cloud_incomplete", []];
            $states[] = "problem";
        }
        if ($conversation === "log") {
            $issues[] = ["readiness_wa_log", []];
            $states[] = "warning";
        }
        if ($notifications === null) {
            $issues[] = ["readiness_wa_notifications_off", []];
            $states[] = "warning";
        } elseif ($notifications === "log") {
            $issues[] = ["readiness_wa_notifications_log", []];
            $states[] = "warning";
        }

        $failed = WhatsAppMessage::where("direction", "out")->where("status", "failed")
            ->where("created_at", ">=", now()->subDays(self::FAILURE_DAYS))->count();
        if ($failed > 0) {
            $issues[] = ["readiness_wa_failures", ["count" => $failed, "days" => self::FAILURE_DAYS]];
            $states[] = "warning";
        }

        return [
            "state" => self::worst($states),
            "own" => $level !== null,
            "conversation" => $conversation,
            "notifications" => $notifications,
            "failed" => $failed,
            "issues" => $issues,
        ];
    }

    private static function worst(array $states): string
    {
        foreach (["problem", "warning"] as $state) {
            if (in_array($state, $states, true)) {
                return $state;
            }
        }

        return "ok";
    }
}
