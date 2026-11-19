<?php

namespace App\Exports;

use App\Models\TrialBalanceArchive;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TrialBalanceExport implements WithMultipleSheets
{
    public function __construct(protected TrialBalanceArchive $archive) {}

    public function sheets(): array
    {
        $this->archive->load(["lines", "period"]);

        return [
            new TrialBalanceSheet($this->archive),
            new MonthlyJournalSheet($this->archive),
        ];
    }
}
