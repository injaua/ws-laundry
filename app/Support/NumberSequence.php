<?php

namespace App\Support;

use App\Models\Store;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Gap-free, collision-free counters for numbered series (ORD…, FT-…, NC…, REC…, CG-…),
 * per store and per series prefix.
 *
 * next() bumps the counter with a single UPDATE … SET last_value = last_value + 1: the
 * InnoDB row stays locked until the surrounding transaction ends, so two cashiers
 * saving at the same moment get consecutive numbers instead of the same one. Call it inside the transaction that
 * inserts the record: if that transaction rolls back, the increment rolls back with it
 * and the number is reused — fiscal series (invoices, credit notes) never skip a number.
 *
 * A series that has no counter yet starts from $seed(): the highest number already
 * issued, so existing data simply continues.
 */
final class NumberSequence
{
    private const TABLE = "number_sequences";

    /** @param  Closure(): int  $seed  highest value already used in this series */
    public static function next(string $series, Closure $seed): int
    {
        $key = [
            "store_id" => CurrentStore::id() ?? Store::defaultId() ?? 0,
            "series" => $series,
        ];

        return DB::transaction(function () use ($key, $seed) {
            if (self::bump($key) === 0) {
                // First number of the series: create the counter (a concurrent request may
                // win the insert, which is fine — both then increment the same row).
                DB::table(self::TABLE)->insertOrIgnore($key + [
                    "last_value" => max(0, (int) $seed()),
                    "created_at" => now(),
                    "updated_at" => now(),
                ]);
                self::bump($key);
            }

            return (int) DB::table(self::TABLE)->where($key)->value("last_value");
        });
    }

    /** "{$prefix}{$n}" with $n zero-padded to $width digits (it may grow past the width). */
    public static function format(string $prefix, int $value, int $width): string
    {
        return $prefix . str_pad((string) $value, $width, "0", STR_PAD_LEFT);
    }

    /** Highest numeric suffix among $numbers that start with $prefix (0 when none). */
    public static function highestSuffix(iterable $numbers, string $prefix): int
    {
        $max = 0;
        foreach ($numbers as $number) {
            $suffix = substr((string) $number, strlen($prefix));
            if ($suffix !== "" && ctype_digit($suffix)) {
                $max = max($max, (int) $suffix);
            }
        }

        return $max;
    }

    private static function bump(array $key): int
    {
        // increment() quotes the column: LAST_VALUE is a reserved word on MySQL 8.
        return DB::table(self::TABLE)->where($key)->increment("last_value", 1, ["updated_at" => now()]);
    }
}
