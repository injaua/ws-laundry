<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Queue worker without Supervisor (shared hosting / cPanel): each minute, process the jobs
// waiting (WhatsApp messages, …) and stop. Harmless next to a permanent `queue:work`.
// In the background so the other tasks of this minute do not wait for it; the lock expires
// after 2 minutes so a crashed run never blocks the queue for long.
Schedule::command("queue:work --stop-when-empty --max-time=50 --tries=4")
    ->everyMinute()
    ->withoutOverlapping(2)
    ->runInBackground();

// Daily WhatsApp reminders (needs the scheduler: `php artisan schedule:run` every minute via cron).
Schedule::command("whatsapp:notify-scheduled")->dailyAt("09:00")->withoutOverlapping();

// WhatsApp by QR code: start the companion service again after a server reboot (or a crash).
Schedule::command("whatsapp:web-service")->everyMinute()->withoutOverlapping();

// Monthly consolidated invoices: drafts for the previous month, reviewed and issued by management.
Schedule::command("invoices:generate-monthly")->monthlyOn(1, "07:00")->withoutOverlapping();

// Mobile money: ask the operators about requests without an answer.
Schedule::command("mobile-money:refresh")->everyMinute()->withoutOverlapping();

// Daily database backup (kept for the retention period set in Security & compliance).
Schedule::command("backup:run")->dailyAt("01:30")->withoutOverlapping();
// Weekly check that nobody changed the audit trail directly in the database.
Schedule::command("audit:verify")->weeklyOn(1, "02:00");
