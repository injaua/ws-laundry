<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\TrialBalanceArchive;
use App\Support\CurrentStore;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    /**
     * Per-account debit/credit movement totals and balances for a date range.
     */
    public function rows(Carbon $from, Carbon $to): Collection
    {
        return DB::table("accounts")
            ->join("journal_lines", "journal_lines.account_id", "=", "accounts.id")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->where("accounts.is_group", false)
            ->whereBetween("journal_entries.entry_date", [$from->copy()->startOfDay()->toDateTimeString(), $to->copy()->endOfDay()->toDateTimeString()])
            ->groupBy("accounts.id", "accounts.code", "accounts.name", "accounts.type")
            ->orderBy("accounts.code")
            ->select(
                "accounts.id",
                "accounts.code",
                "accounts.name",
                "accounts.type",
                DB::raw("SUM(journal_lines.debit) as total_debit"),
                DB::raw("SUM(journal_lines.credit) as total_credit")
            )
            ->get()
            ->map(function ($row) {
                $difference = (float) $row->total_debit - (float) $row->total_credit;
                $row->balance_debit = $difference > 0 ? $difference : 0;
                $row->balance_credit = $difference < 0 ? -$difference : 0;

                return $row;
            });
    }

    public function totals(Collection $rows): array
    {
        return [
            "debit" => (float) $rows->sum("total_debit"),
            "credit" => (float) $rows->sum("total_credit"),
            "balance_debit" => (float) $rows->sum("balance_debit"),
            "balance_credit" => (float) $rows->sum("balance_credit"),
        ];
    }

    /**
     * Freeze the year-to-date trial balance of a period into the archive.
     */
    public function archive(AccountingPeriod $period): TrialBalanceArchive
    {
        $from = Carbon::create($period->year, 1, 1);
        $to = $period->monthEnd();
        $rows = $this->rows($from, $to);
        $totals = $this->totals($rows);

        $archive = TrialBalanceArchive::create([
            "accounting_period_id" => $period->id,
            "year" => $period->year,
            "month" => $period->month,
            "from_date" => $from->toDateString(),
            "to_date" => $to->toDateString(),
            "total_debit" => Money::round($totals["debit"]),
            "total_credit" => Money::round($totals["credit"]),
            "created_by" => auth()->id(),
        ]);

        foreach ($rows as $row) {
            $archive->lines()->create([
                "account_code" => $row->code,
                "account_name" => $row->name,
                "account_type" => $row->type,
                "total_debit" => Money::round($row->total_debit),
                "total_credit" => Money::round($row->total_credit),
                "balance_debit" => Money::round($row->balance_debit),
                "balance_credit" => Money::round($row->balance_credit),
            ]);
        }

        return $archive;
    }
}
