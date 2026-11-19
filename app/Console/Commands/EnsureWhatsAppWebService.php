<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppWebService;
use Illuminate\Console\Command;

/** Keeps the WhatsApp QR service running while "QR code" is the conversations channel (every minute). */
class EnsureWhatsAppWebService extends Command
{
    protected $signature = "whatsapp:web-service {--stop : Stop the service}";

    protected $description = "Start the WhatsApp QR-code service (whatsapp-web/) when it is the chosen channel and not running";

    public function handle(WhatsAppWebService $service): int
    {
        if ($this->option("stop")) {
            $service->stop();
            $this->info("Stopped.");

            return self::SUCCESS;
        }

        if (config("whatsapp.driver") !== "web" || !$service->enabled()) {
            return self::SUCCESS; // another channel, or the service is run by pm2 / systemd / NSSM
        }

        $result = $service->start();
        if ($result === "started") {
            $this->info(__("app.wa_web_autostart_started"));
        }

        return $result === "failed" ? self::FAILURE : self::SUCCESS;
    }
}
