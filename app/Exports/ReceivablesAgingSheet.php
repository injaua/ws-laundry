<?php

namespace App\Exports;

use App\Support\Money;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReceivablesAgingSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected Collection $aging) {}

    public function title(): string
    {
        return __("app.aging_report");
    }

    public function headings(): array
    {
        return [
            __("app.customer"),
            __("app.credit_limit"),
            __("app.orders"),
            __("app.bucket_current"),
            __("app.bucket_1_30"),
            __("app.bucket_31_60"),
            __("app.bucket_61_90"),
            __("app.bucket_90_plus"),
            __("app.total_outstanding"),
            __("app.overdue_amount"),
        ];
    }

    public function array(): array
    {
        $rows = $this->aging->map(fn (array $row) => [
            $row["customer"]?->name ?? "-",
            Money::round($row["credit_limit"]),
            $row["count"],
            Money::round($row["buckets"]["current"]),
            Money::round($row["buckets"]["d1_30"]),
            Money::round($row["buckets"]["d31_60"]),
            Money::round($row["buckets"]["d61_90"]),
            Money::round($row["buckets"]["d90_plus"]),
            Money::round($row["total"]),
            Money::round($row["overdue"]),
        ])->all();

        $totals = array_fill(0, 10, 0);
        foreach ($rows as $row) {
            $totals[2] += $row[2];
            for ($i = 3; $i <= 9; $i++) {
                $totals[$i] += $row[$i];
            }
        }

        $rows[] = [__("app.total"), "", $totals[2], $totals[3], $totals[4], $totals[5], $totals[6], $totals[7], $totals[8], $totals[9]];

        return $rows;
    }
}
