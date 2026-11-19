<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Development / pre-approval driver: messages stay in the platform (and the log)
 * and are reported as sent, so the inbox and notifications can be used end to end.
 */
class LogGateway implements WhatsAppGateway
{
    public function sendText(string $to, string $body): string
    {
        Log::info("[whatsapp:log] text to {$to}", ["body" => $body]);

        return "log-" . Str::uuid();
    }

    public function sendTemplate(string $to, string $template, string $language, array $parameters): string
    {
        Log::info("[whatsapp:log] template {$template} ({$language}) to {$to}", ["parameters" => $parameters]);

        return "log-" . Str::uuid();
    }

    public function isLive(): bool
    {
        return false;
    }

    public function isOfficial(): bool
    {
        return false;
    }

    public function channel(): string
    {
        return "log";
    }
}
