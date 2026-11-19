<?php

namespace App\Http\Requests\Concerns;

use App\Models\AccountingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Shared rules for money and quantity fields, bounded by the database columns, and the
 * closed-accounting-period check as a validation error.
 */
trait ValidatesAmounts
{
    /** Largest value a DECIMAL(12, 2) amount column holds. */
    protected const MAX_AMOUNT = "9999999999.99";

    /** Largest value a DECIMAL(8, 2) quantity column holds. */
    protected const MAX_QUANTITY = "999999.99";

    /** An amount in meticais with at most two decimals (cents). */
    protected function amountRules(string $min = "0", bool $required = true): array
    {
        return [$required ? "required" : "nullable", "numeric", "decimal:0,2", "min:{$min}", "max:" . self::MAX_AMOUNT];
    }

    /** A quantity (kg, units, litres…) with at most two decimals. */
    protected function quantityRules(string $min = "0.01"): array
    {
        return ["required", "numeric", "decimal:0,2", "min:{$min}", "max:" . self::MAX_QUANTITY];
    }

    /** Adds an error on $field when $date falls in a closed accounting period. */
    protected function rejectClosedPeriod(Validator $validator, mixed $date, string $field): void
    {
        if (blank($date) || $validator->errors()->has($field) || !AccountingPeriod::closedOn($date)) {
            return;
        }

        $date = $date instanceof Carbon ? $date : Carbon::parse($date);
        $validator->errors()->add($field, __("app.period_closed", [
            "period" => sprintf("%02d/%04d", $date->month, $date->year),
        ]));
    }
}
