<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\Payment;
use App\Support\CurrentStore;
use Illuminate\Console\Command;

class SyncPaymentJournal extends Command
{
    protected $signature = 'accounting:sync-payments';

    protected $description = 'Repost journal entries for payments, including legacy ones posted before automatic journaling';

    public function handle(): int
    {
        $posted = 0;
        $noAccount = 0;
        $trashedOrder = 0;
        $lockedPeriod = 0;

        // Each store posts to its own chart of accounts and periods.
        CurrentStore::each(fn () => Payment::orderBy("id")->chunkById(200, function ($payments) use (&$posted, &$noAccount, &$trashedOrder, &$lockedPeriod) {
            foreach ($payments as $payment) {
                if (AccountingPeriod::closedOn($payment->paid_at)) {
                    $lockedPeriod++;
                } elseif (!$payment->order) {
                    $trashedOrder++;
                } elseif ($payment->postToJournal() === null) {
                    $noAccount++;
                } else {
                    $posted++;
                }
            }
        }));

        $this->info("{$posted} lançamentos sincronizados.");

        if ($lockedPeriod > 0) {
            $this->warn("{$lockedPeriod} pagamentos ignorados: período contabilístico fechado.");
        }
        if ($trashedOrder > 0) {
            $this->warn("{$trashedOrder} pagamentos ignorados: a ordem está na reciclagem (serão lançados se for restaurada).");
        }
        if ($noAccount > 0) {
            $this->warn("{$noAccount} pagamentos por lançar: falta a conta correspondente no plano de contas.");
        }

        return self::SUCCESS;
    }
}
