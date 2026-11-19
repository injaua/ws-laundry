<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Support\StoreSettings;

/**
 * Payment methods accepted for new payments. Company-wide by default; a store with its own
 * payment configuration (StoreSettings "payments") has its own list. Payments already
 * recorded keep their method, and reports / till counts always list every method.
 */
class PaymentMethods
{
    /**
     * @param  int|string|null  $level  "current" = the store worked in; null = company; a store id = its own list
     * @return string[] in the order of Payment::METHODS
     */
    public static function enabled(int|string|null $level = "current"): array
    {
        $level = $level === "current" ? StoreSettings::currentLevel("payments") : $level;
        $stored = StoreSettings::values("payments", $level)["payment_methods"] ?? null;

        if ($stored === null || $stored === "") {
            return Payment::METHODS; // never configured: everything is accepted
        }

        $chosen = array_values(array_intersect(Payment::METHODS, explode(",", (string) $stored)));

        return $chosen === [] ? Payment::METHODS : $chosen;
    }

    public static function accepts(string $method): bool
    {
        return in_array($method, self::enabled(), true);
    }

    /** @param  string[]  $methods  at least one */
    public static function save(array $methods, ?int $level): void
    {
        if ($level !== null) {
            StoreSettings::takeOver("payments", $level);
        }

        StoreSettings::set("payments", $level, "payment_methods", implode(",", array_values(array_intersect(Payment::METHODS, $methods))));
    }
}
