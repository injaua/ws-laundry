<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Free connection by QR code: a normal WhatsApp number linked like WhatsApp Web,
 * through the companion service in whatsapp-web/ (unofficial Baileys library).
 *
 * No 24h window and no templates, but WhatsApp may block numbers that use it, so
 * the platform only uses it for conversations, never for automatic notifications.
 */
class WebSessionGateway implements WhatsAppGateway
{
    public function __construct(private array $config) {}

    public function sendText(string $to, string $body): string
    {
        $response = $this->request("post", "/send", ["to" => $to, "text" => $body]);

        if ($response->successful() && filled($response->json("id"))) {
            return (string) $response->json("id");
        }

        // 503: the phone is not linked right now; worth retrying once someone scans the QR.
        throw new WhatsAppException(
            (string) ($response->json("error") ?: "WhatsApp QR: HTTP {$response->status()}"),
            retryable: $response->status() === 503 || $response->serverError(),
        );
    }

    public function sendTemplate(string $to, string $template, string $language, array $parameters): string
    {
        throw new WhatsAppException(__("app.wa_web_no_templates"));
    }

    public function isLive(): bool
    {
        return true;
    }

    public function isOfficial(): bool
    {
        return false;
    }

    public function channel(): string
    {
        return "web";
    }

    /** ["status" => starting|qr|open|reconnecting|logged_out|offline, "qr" => data URL, "me" => number] */
    public function status(): array
    {
        try {
            $response = $this->request("get", "/status", timeout: 3);
        } catch (WhatsAppException) {
            return ["status" => "offline", "qr" => null, "me" => null];
        }

        return $response->successful()
            ? $response->json() + ["qr" => null, "me" => null]
            : ["status" => $response->status() === 401 ? "unauthorized" : "offline", "qr" => null, "me" => null];
    }

    /** Unlink the phone; the service then shows a new QR code. */
    public function logout(): void
    {
        $this->request("post", "/logout");
    }

    private function request(string $method, string $path, array $data = [], ?int $timeout = null): Response
    {
        try {
            return Http::withToken((string) $this->config["token"])
                ->acceptJson()
                ->timeout($timeout ?? (int) ($this->config["timeout"] ?? 20))
                ->{$method}(rtrim((string) $this->config["url"], "/") . $path, $data);
        } catch (ConnectionException) {
            throw new WhatsAppException(__("app.wa_web_service_down"), retryable: true);
        }
    }
}
