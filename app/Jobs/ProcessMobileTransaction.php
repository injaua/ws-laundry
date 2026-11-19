<?php

namespace App\Jobs;

use App\Models\MobileTransaction;
use App\Services\MobileMoney\MobilePayments;
use App\Support\CurrentStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends a mobile money request to the operator (dispatched after the HTTP response). */
class ProcessMobileTransaction implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $transactionId) {}

    public function handle(MobilePayments $payments): void
    {
        // Queue workers have no store: work in the store of the request.
        if ($tx = MobileTransaction::acrossStores()->find($this->transactionId)) {
            CurrentStore::run($tx->store_id, fn () => $payments->process($tx));
        }
    }
}
