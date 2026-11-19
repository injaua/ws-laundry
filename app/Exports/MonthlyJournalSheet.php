<?php

namespace App\Exports;

use App\Models\TrialBalanceArchive;
use App\Support\CurrentStore;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class MonthlyJournalSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(protected TrialBalanceArchive $archive) {}

    public function title(): string
    {
        return sprintf("%s %02d-%04d", __("app.journal_entries"), $this->archive->month, $this->archive->year);
    }

    public function headings(): array
    {
        return [
            __("app.journal_number"),
            __("app.date"),
            __("app.description"),
            __("app.code"),
            __("app.account"),
            __("app.total_debit"),
            __("app.total_credit"),
        ];
    }

    public function collection(): Collection
    {
        $from = Carbon::create($this->archive->year, $this->archive->month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return DB::table("journal_lines")
            ->join("journal_entries", "journal_entries.id", "=", "journal_lines.journal_entry_id")
            ->where("journal_entries.store_id", CurrentStore::id())
            ->join("accounts", "accounts.id", "=", "journal_lines.account_id")
            ->whereBetween("journal_entries.entry_date", [$from->toDateString(), $to->toDateString()])
            ->orderBy("journal_entries.entry_date")
            ->orderBy("journal_entries.id")
            ->select("journal_entries.entry_number", "journal_entries.entry_date", "journal_entries.description", "accounts.code", "accounts.name", "journal_lines.debit", "journal_lines.credit")
            ->get()
            ->map(fn ($line) => [
                $line->entry_number,
                Carbon::parse($line->entry_date)->format(date_fmt()),
                $line->description,
                $line->code,
                $line->name,
                Money::round($line->debit),
                Money::round($line->credit),
            ]);
    }
}
