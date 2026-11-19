<?php

namespace App\Exports;

use App\Services\AccountsReceivable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReceivablesExport implements WithMultipleSheets
{
    public function __construct(protected AccountsReceivable $ar) {}

    public function sheets(): array
    {
        $orders = $this->ar->outstandingOrders();

        return [
            new ReceivablesAgingSheet($this->ar->agingByCustomer($orders)),
            new ReceivablesOrdersSheet($orders),
        ];
    }
}
