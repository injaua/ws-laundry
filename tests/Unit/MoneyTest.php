<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Money does its arithmetic on exact decimals: binary float noise never turns
 * into a wrong cent, a false "unbalanced" or a phantom balance.
 */
class MoneyTest extends TestCase
{
    public function test_sums_are_exact_where_float_addition_is_not(): void
    {
        $this->assertNotSame(0.3, 0.1 + 0.2);
        $this->assertSame(0.3, Money::add(0.1, 0.2));

        // Ten payments of 0.10 settle an order of 1.00 exactly.
        $payments = array_fill(0, 10, "0.10");
        $this->assertTrue(Money::isZero(Money::sub("1.00", Money::sum($payments))));
        $this->assertSame(0, Money::compare(Money::sum($payments), 1));
    }

    public function test_accepts_database_strings_floats_ints_and_null(): void
    {
        $this->assertSame(1234.5, Money::round("1234.50"));
        $this->assertSame(10.0, Money::round(10));
        $this->assertSame(0.0, Money::round(null));
        $this->assertSame(0.0, Money::round(""));
        $this->assertSame("1234.50", Money::toString(1234.5));
    }

    public function test_rounds_half_away_from_zero_to_the_cent(): void
    {
        $this->assertSame(2.68, Money::round(2.675));
        $this->assertSame(1.01, Money::round("1.005"));
        $this->assertSame(-1.01, Money::round("-1.005"));
        $this->assertSame(0.0, Money::round(0.004));
    }

    public function test_subtraction_and_comparisons_ignore_float_noise(): void
    {
        $remaining = Money::sub(100, 33.33, 33.33, 33.34);
        $this->assertSame(0.0, $remaining);
        $this->assertFalse(Money::isPositive($remaining));
        $this->assertFalse(Money::isNegative($remaining));

        $this->assertTrue(Money::equals(0.1 + 0.2, 0.3));
        $this->assertSame(-1, Money::compare("99.99", 100));
        $this->assertSame(1, Money::compare(0.1 + 0.2 + 0.01, 0.3));
        $this->assertFalse(Money::isPositive(0.004));
        $this->assertTrue(Money::isPositive(0.005));
    }

    public function test_percent_discount_multiplication_and_division(): void
    {
        $this->assertSame(160.0, Money::percent(1000, 16));
        $this->assertSame(17.27, Money::percent("107.95", 16));
        $this->assertSame(84.99, Money::discount(99.99, 15));
        $this->assertSame(16.66, Money::mul("12.50", 1.333));
        $this->assertSame(333.33, Money::div(1000, 3));
        $this->assertSame(5.0, Money::abs(-5));
        $this->assertSame(2.5, Money::max(1, "2.5"));
        $this->assertSame(0.0, Money::min(0, 3));
    }

    public function test_split_gross_always_re_sums_to_the_gross(): void
    {
        $this->assertSame([100.0, 16.0], Money::splitGross(116, 16));
        $this->assertSame([50.0, 0.0], Money::splitGross(50, 0));

        foreach (["0.01", "0.07", "1.00", "99.99", "1234.57", "999999.99"] as $gross) {
            [$net, $vat] = Money::splitGross($gross, 16);
            $this->assertTrue(Money::equals(Money::add($net, $vat), $gross), "gross {$gross}");
            $this->assertFalse(Money::isNegative($vat));
        }
    }

    public function test_cents_round_trip(): void
    {
        $this->assertSame(1235, Money::cents("12.345"));
        $this->assertSame(12.35, Money::fromCents(1235));
        $this->assertSame(-50, Money::cents(-0.5));
    }

    public function test_large_amounts_keep_every_cent(): void
    {
        $this->assertSame(9999999999.99, Money::add("9999999999.98", "0.01"));
        $this->assertSame("12345678901.23", Money::toString(Money::round("12345678901.23")));
    }
}
