<?php

namespace App\Console\Commands;

use App\Services\Invoicing;
use App\Support\CurrentStore;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = "invoices:generate-monthly {month? : Month to bill, YYYY-MM (default: previous month)}";

    protected $description = "Create draft consolidated invoices for monthly-billed credit customers";

    public function handle(Invoicing $invoicing): int
    {
        $month = $this->argument("month")
            ? Carbon::createFromFormat("Y-m", $this->argument("month"))->startOfMonth()
            : today()->subMonthNoOverflow()->startOfMonth();

        // Each store bills its own customers, with its own invoice numbers.
        $count = 0;
        CurrentStore::each(function ($store) use ($invoicing, $month, &$count) {
            $invoices = $invoicing->generateMonthly($month);
            foreach ($invoices as $invoice) {
                $this->line(sprintf("  [%s] %s — %s (%d)", $store->code, $invoice->customer->name, money((float) $invoice->total), $invoice->orders()->count()));
            }
            $count += $invoices->count();
        });

        $this->info($count . " draft invoice(s) created for " . $month->format("m/Y") . ". Review and issue them under Invoices.");

        return self::SUCCESS;
    }
}
