<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\Expense;
use App\Support\CurrentStore;
use Illuminate\Console\Command;

class SyncExpenseJournal extends Command
{
    protected $signature = 'accounting:sync-expenses';

    protected $description = 'Repost journal entries for expenses, including ones whose category only later got an account';

    public function handle(): int
    {
        $posted = 0;
        $unlinked = 0;
        $lockedPeriod = 0;

        // Each store posts to its own chart of accounts and periods.
        CurrentStore::each(fn () => Expense::orderBy("id")->chunkById(200, function ($expenses) use (&$posted, &$unlinked, &$lockedPeriod) {
            foreach ($expenses as $expense) {
                if (AccountingPeriod::closedOn($expense->expense_date)) {
                    $lockedPeriod++;
                } elseif ($expense->postToJournal() === null) {
                    $unlinked++;
                } else {
                    $posted++;
                }
            }
        }));

        $this->info("{$posted} lançamentos sincronizados.");

        if ($lockedPeriod > 0) {
            $this->warn("{$lockedPeriod} despesas ignoradas: período contabilístico fechado.");
        }
        if ($unlinked > 0) {
            $this->warn("{$unlinked} despesas por lançar: a categoria não tem conta ligada.");
        }

        return self::SUCCESS;
    }
}
