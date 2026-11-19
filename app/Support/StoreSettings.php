<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\Store;
use App\Services\MobileMoney\MobileMoneySettings;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Settings a store either takes from the company or configures as a whole for itself:
 *
 *  - "whatsapp": conversation / notification channels and the official API credentials
 *    (a store with its own configuration also gets its own QR-code session, i.e. its own number);
 *  - "payments": accepted payment methods and the M-Pesa / e-Mola credentials and modes.
 *
 * The group is all-or-nothing, so a store never mixes its own values with the company's
 * (e.g. its own shortcode with the company's API key). Taking a group over copies the company
 * values into the store as a starting point; releasing it deletes the store's values.
 */
final class StoreSettings
{
    public const GROUPS = ["whatsapp", "payments"];

    /** @return string[] keys of a group */
    public static function keys(string $group): array
    {
        return match ($group) {
            "whatsapp" => WhatsAppSettings::KEYS,
            "payments" => ["payment_methods", ...MobileMoneySettings::keys()],
            default => throw new InvalidArgumentException("Unknown settings group {$group}"),
        };
    }

    private static function marker(string $group): string
    {
        return "own_{$group}";
    }

    /** The store configures this group itself (instead of using the company's). */
    public static function owns(string $group, ?int $storeId): bool
    {
        if (!$storeId) {
            return false;
        }

        return (string) (SiteSetting::valuesAt($storeId, [self::marker($group)])[self::marker($group)] ?? "") === "1";
    }

    /** Level the group is read from for a store: the store when it has its own configuration, else the company (null). */
    public static function levelFor(string $group, ?int $storeId): ?int
    {
        return self::owns($group, $storeId) ? $storeId : null;
    }

    /** Level the group is read from in the store worked in now. */
    public static function currentLevel(string $group): ?int
    {
        return self::levelFor($group, CurrentStore::id());
    }

    /** @return array<string, mixed> the group's values stored at that level */
    public static function values(string $group, ?int $level): array
    {
        return SiteSetting::valuesAt($level, self::keys($group));
    }

    public static function set(string $group, ?int $level, string $key, mixed $value): void
    {
        if (!in_array($key, self::keys($group), true)) {
            throw new InvalidArgumentException("{$key} is not a {$group} setting");
        }
        SiteSetting::setAt($level, $key, $value);
    }

    /** The store starts configuring the group itself, from a copy of the company values. */
    public static function takeOver(string $group, int $storeId): void
    {
        if (self::owns($group, $storeId)) {
            return;
        }

        DB::transaction(function () use ($group, $storeId) {
            $own = SiteSetting::valuesAt($storeId, self::keys($group));
            foreach (self::values($group, null) as $key => $value) {
                if (!array_key_exists($key, $own)) {
                    SiteSetting::setAt($storeId, $key, $value);
                }
            }
            SiteSetting::setAt($storeId, self::marker($group), "1");
        });
    }

    /** The store goes back to the company configuration; its own values are deleted. */
    public static function release(string $group, int $storeId): void
    {
        SiteSetting::forgetAt($storeId, [...self::keys($group), self::marker($group)]);
    }

    /** @return int[] ids of the active stores that configure the group themselves */
    public static function owners(string $group): array
    {
        return SiteSetting::where("key", self::marker($group))->where("value", "1")->whereNotNull("store_id")
            ->whereIn("store_id", Store::active()->select("id"))
            ->pluck("store_id")->map(fn ($id) => (int) $id)->all();
    }
}
