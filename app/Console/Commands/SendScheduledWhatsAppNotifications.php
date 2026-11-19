<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\AutomaticNotifications;
use App\Support\CurrentStore;
use Illuminate\Console\Command;

class SendScheduledWhatsAppNotifications extends Command
{
    protected $signature = "whatsapp:notify-scheduled";

    protected $description = "Queue the daily WhatsApp notifications: credit due soon, overdue invoices and items not collected";

    public function handle(AutomaticNotifications $notifications): int
    {
        // Each store has its own notification settings, templates and customers.
        CurrentStore::each(function ($store) use ($notifications) {
            foreach ($notifications->runScheduled() as $event => $count) {
                $this->line("[{$store->code}] {$event}: {$count}");
            }
        });

        return self::SUCCESS;
    }
}
