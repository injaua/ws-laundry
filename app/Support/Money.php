<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Monetary arithmetic. Every amount is stored as DECIMAL(12, 2) and cast to
 * "decimal:2" (a string such as "1234.50"), so the database is exact; this
 * class keeps PHP exact too. Every operation runs on arbitrary-precision
 * decimals (brick/math) and rounds the result to cents, half away from zero.
 *
 * Results come back as a float already rounded to cents: amounts below
 * 10^13 survive the float round-trip unchanged, so storing, comparing or
 * displaying them is safe. Never add, subtract or compare amounts with the
 * plain PHP operators — use add(), sub(), sum(), compare(), equals() — so
 * binary float noise (0.1 + 0.2 = 0.30000000000000004) can never accumulate
 * into a wrong cent. How many decimals are *shown* is a separate display
 * setting (Admin → System settings → Localization).
 */
final class Money
{
    /** Decimal places of every stored amount. */
    public const SCALE = 2;

    /** Model cast for amount columns. */
    public const CAST = "decimal:2";

    /** Exact decimal value of an amount (not rounded). Null and "" are zero. */
    public static function of(BigDecimal|float|int|string|null $value): BigDecimal
    {
        if ($value instanceof BigDecimal) {
            return $value;
        }

        if ($value === null || $value === "") {
            return BigDecimal::zero();
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException("Amount is not a finite number.");
            }

            // 15 significant digits is what a double holds exactly: this drops the
            // binary noise (0.30000000000000004 → 0.3) without depending on php.ini.
            $value = sprintf("%.15G", $value);
        }

        return BigDecimal::of(is_string($value) ? trim($value) : $value);
    }

    /** Amount rounded to cents, as an exact decimal. */
    public static function decimal(BigDecimal|float|int|string|null $value): BigDecimal
    {
        return self::of($value)->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    public static function round(BigDecimal|float|int|string|null $value): float
    {
        return self::decimal($value)->toFloat();
    }

    /** Amount rounded to cents as a string ("1234.50"), the format the database stores. */
    public static function toString(BigDecimal|float|int|string|null $value): string
    {
        return (string) self::decimal($value);
    }

    /** Amount in whole cents (gateways and integer comparisons). */
    public static function cents(BigDecimal|float|int|string|null $value): int
    {
        return self::decimal($value)->getUnscaledValue()->toInt();
    }

    public static function fromCents(int $cents): float
    {
        return BigDecimal::ofUnscaledValue($cents, self::SCALE)->toFloat();
    }

    /** Sum of amounts, rounded to cents. */
    public static function sum(iterable $values): float
    {
        $total = BigDecimal::zero();
        foreach ($values as $value) {
            $total = $total->plus(self::of($value));
        }

        return self::round($total);
    }

    /** $a + $b + …, rounded to cents. */
    public static function add(BigDecimal|float|int|string|null ...$values): float
    {
        return self::sum($values);
    }

    /** $from − each of $values, rounded to cents. */
    public static function sub(BigDecimal|float|int|string|null $from, BigDecimal|float|int|string|null ...$values): float
    {
        $result = self::of($from);
        foreach ($values as $value) {
            $result = $result->minus(self::of($value));
        }

        return self::round($result);
    }

    /** $amount × $factor (a quantity, a weight…), rounded to cents. */
    public static function mul(BigDecimal|float|int|string|null $amount, BigDecimal|float|int|string|null $factor): float
    {
        return self::round(self::of($amount)->multipliedBy(self::of($factor)));
    }

    /** $amount divided by $divisor (a daily rate, an even share...), rounded to cents. */
    public static function div(BigDecimal|float|int|string|null $amount, BigDecimal|float|int|string|null $divisor): float
    {
        return self::round(self::of($amount)->dividedBy(self::of($divisor), self::SCALE, RoundingMode::HalfUp));
    }

    /** $rate % of $amount (VAT on a net amount, a percentage surcharge…). */
    public static function percent(BigDecimal|float|int|string|null $amount, BigDecimal|float|int|string|null $rate): float
    {
        return self::round(self::percentOf($amount, $rate));
    }

    /** $amount less $rate % (a percentage discount), rounded to cents. */
    public static function discount(BigDecimal|float|int|string|null $amount, BigDecimal|float|int|string|null $rate): float
    {
        return self::round(self::of($amount)->minus(self::percentOf($amount, $rate)));
    }

    /**
     * Split a gross (VAT-inclusive) amount into [net, vat] so that net + vat
     * always re-sums to the gross, VAT never negative nor above the gross.
     *
     * @return array{0: float, 1: float}
     */
    public static function splitGross(BigDecimal|float|int|string|null $gross, BigDecimal|float|int|string|null $rate): array
    {
        $gross = self::decimal($gross);
        $rate = self::of($rate);

        if ($rate->isNegativeOrZero()) {
            return [$gross->toFloat(), 0.0];
        }

        $vat = $gross->multipliedBy($rate)
            ->dividedBy($rate->plus(100), self::SCALE, RoundingMode::HalfUp);
        $vat = BigDecimal::max(BigDecimal::zero(), BigDecimal::min($gross, $vat));

        return [$gross->minus($vat)->toFloat(), $vat->toFloat()];
    }

    /** -1, 0 or 1 as $a is below, equal to or above $b — to the cent. */
    public static function compare(BigDecimal|float|int|string|null $a, BigDecimal|float|int|string|null $b): int
    {
        return self::decimal($a)->compareTo(self::decimal($b));
    }

    /** Equality to the cent, immune to float noise. */
    public static function equals(BigDecimal|float|int|string|null $a, BigDecimal|float|int|string|null $b): bool
    {
        return self::compare($a, $b) === 0;
    }

    /** At least one cent above zero. */
    public static function isPositive(BigDecimal|float|int|string|null $value): bool
    {
        return self::decimal($value)->isPositive();
    }

    /** At least one cent below zero. */
    public static function isNegative(BigDecimal|float|int|string|null $value): bool
    {
        return self::decimal($value)->isNegative();
    }

    /** Zero once rounded to cents. */
    public static function isZero(BigDecimal|float|int|string|null $value): bool
    {
        return self::decimal($value)->isZero();
    }

    /** Absolute value, rounded to cents. */
    public static function abs(BigDecimal|float|int|string|null $value): float
    {
        return self::round(self::of($value)->abs());
    }

    /** Larger of the amounts, rounded to cents. */
    public static function max(BigDecimal|float|int|string|null ...$values): float
    {
        return self::round(BigDecimal::max(...array_map(self::of(...), $values)));
    }

    /** Smaller of the amounts, rounded to cents. */
    public static function min(BigDecimal|float|int|string|null ...$values): float
    {
        return self::round(BigDecimal::min(...array_map(self::of(...), $values)));
    }

    private static function percentOf(BigDecimal|float|int|string|null $amount, BigDecimal|float|int|string|null $rate): BigDecimal
    {
        // Dividing by 100 is always exact: no intermediate rounding.
        return self::of($amount)->multipliedBy(self::of($rate))->withPointMovedLeft(2);
    }
}
