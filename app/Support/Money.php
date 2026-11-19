<?php

namespace App\Support;

/**
 * Monetary arithmetic. Every amount is stored as DECIMAL(12, 2) and cast to
 * "decimal:2", so all calculations round to cents (half away from zero) with
 * this class — never to whole units. How many decimals are *shown* is a
 * separate display setting (Admin → System settings → Localization).
 */
final class Money
{
    /** Decimal places of every stored amount. */
    public const SCALE = 2;

    /** Model cast for amount columns. */
    public const CAST = "decimal:2";

    public static function round(float|int|string|null $value): float
    {
        return round((float) $value, self::SCALE);
    }

    /** Sum of amounts, rounded to cents. */
    public static function sum(iterable $values): float
    {
        $total = 0.0;
        foreach ($values as $value) {
            $total += (float) $value;
        }

        return self::round($total);
    }

    /** $rate % of $amount (VAT on a net amount, a percentage surcharge…). */
    public static function percent(float|int|string|null $amount, float|int|string|null $rate): float
    {
        return self::round((float) $amount * (float) $rate / 100);
    }

    /**
     * Split a gross (VAT-inclusive) amount into [net, vat] so that net + vat
     * always re-sums to the gross, VAT never negative nor above the gross.
     *
     * @return array{0: float, 1: float}
     */
    public static function splitGross(float|int|string|null $gross, float|int|string|null $rate): array
    {
        $gross = self::round($gross);
        $rate = (float) $rate;

        if ($rate <= 0) {
            return [$gross, 0.0];
        }

        $vat = max(0.0, min($gross, self::round($gross * $rate / (100 + $rate))));

        return [self::round($gross - $vat), $vat];
    }

    /** Equality to the cent, immune to float noise. */
    public static function equals(float|int|string|null $a, float|int|string|null $b): bool
    {
        return abs((float) $a - (float) $b) < 0.005;
    }
}
