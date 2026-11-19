<?php

namespace App\Console\Commands;

use App\Models\MobileTransaction;
use App\Services\MobileMoney\MobilePayments;
use App\Support\CurrentStore;
use Illuminate\Console\Command;

/** Asks the operators about mobile payments still without an answer and expires old ones. */
class RefreshMobileTransactions extends Command
{
    protected $signature = "mobile-money:refresh";

    protected $description = "Check pending M-Pesa / e-Mola requests with the operators";

    public function handle(MobilePayments $payments): int
    {
        $open = MobileTransaction::acrossStores()->open()->where("updated_at", "<=", now()->subMinute())->orderBy("id")->limit(50)->get();

        foreach ($open as $tx) {
            // The resulting payment belongs to the store of the request.
            CurrentStore::run($tx->store_id, fn () => $payments->refresh($tx));
        }

        $this->info("Checked {$open->count()} request(s).");

        return self::SUCCESS;
    }
}
