<?php

namespace App\Exports;

use App\Models\TrialBalanceArchive;
use App\Support\Money;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class TrialBalanceSheet implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected TrialBalanceArchive $archive) {}

    public function title(): string
    {
        return sprintf("%s %02d-%04d", __("app.trial_balance"), $this->archive->month, $this->archive->year);
    }

    public function headings(): array
    {
        return [
            __("app.code"),
            __("app.account"),
            __("app.account_type"),
            __("app.total_debit"),
            __("app.total_credit"),
            __("app.balance_debit"),
            __("app.balance_credit"),
        ];
    }

    public function array(): array
    {
        $rows = $this->archive->lines->map(fn ($line) => [
            $line->account_code,
            $line->account_name,
            __("app.account_type_" . $line->account_type),
            Money::round($line->total_debit),
            Money::round($line->total_credit),
            Money::round($line->balance_debit),
            Money::round($line->balance_credit),
        ])->all();

        $rows[] = ["", "", "", Money::round($this->archive->total_debit), Money::round($this->archive->total_credit), "", ""];

        return $rows;
    }
}
