<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Illuminate\Console\Command;

class SyncExpenseJournal extends Command
{
    protected $signature = 'accounting:sync-expenses';
    protected $description = 'Repost journal entries for expenses, including ones whose category only later got an account';

    public function handle(): int
    {
        $posted = 0;
        $unlinked = 0;

        Expense::orderBy("id")->chunkById(200, function ($expenses) use (&$posted, &$unlinked) {
            foreach ($expenses as $expense) {
                if ($expense->postToJournal() === null) {
                    $unlinked++;
                } else {
                    $posted++;
                }
            }
        });

        $this->info("{$posted} lançamentos sincronizados.");

        if ($unlinked > 0) {
            $this->warn("{$unlinked} despesas por lançar: a categoria não tem conta ligada.");
        }

        return self::SUCCESS;
    }
}
