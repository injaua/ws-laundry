<?php

use App\Support\SystemSettings;

/*
 * Formatting helpers that follow Admin → System settings → Localization.
 */

if (!function_exists("money")) {
    /** 1250 -> "1,250 Mts" (symbol, position, decimals and separators from the settings). */
    function money(float|int|string|null $value, ?int $decimals = null): string
    {
        return SystemSettings::money($value, $decimals);
    }
}

if (!function_exists("num")) {
    /** A number with the configured separators. */
    function num(float|int|string|null $value, int $decimals = 0): string
    {
        return SystemSettings::number($value, $decimals);
    }
}

if (!function_exists("qty")) {
    /** A quantity or rate without trailing zeros, with the configured separators. */
    function qty(float|int|string|null $value, int $decimals = 2): string
    {
        return SystemSettings::quantity($value, $decimals);
    }
}

if (!function_exists("currency_symbol")) {
    function currency_symbol(): string
    {
        return SystemSettings::currencySymbol();
    }
}

if (!function_exists("date_fmt")) {
    /** The configured PHP date format, for ->format(date_fmt()). */
    function date_fmt(): string
    {
        return SystemSettings::dateFormat();
    }
}

if (!function_exists("datetime_fmt")) {
    function datetime_fmt(): string
    {
        return SystemSettings::dateTimeFormat();
    }
}

if (!function_exists("request_date")) {
    /**
     * A date filter from the query string, or $default when it is missing or not a valid
     * date (e.g. ?from=abc, ?to=2026-99-99, ?from[]=x) — a bad link must not crash the page.
     */
    function request_date(string $key, ?Carbon\Carbon $default = null): ?Carbon\Carbon
    {
        $value = request()->input($key);
        if (!is_string($value) || trim($value) === "") {
            return $default;
        }

        try {
            $date = Carbon\Carbon::parse(trim($value));
        } catch (Throwable) {
            return $default;
        }

        // Out-of-range years usually come from typos and break date columns.
        return $date->year >= 1900 && $date->year <= 2200 ? $date : $default;
    }
}

if (!function_exists("is_rtl")) {
    function is_rtl(): bool
    {
        return SystemSettings::isRtl();
    }
}
