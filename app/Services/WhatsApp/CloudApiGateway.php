<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/** WhatsApp Business Cloud API (Meta Graph API). */
class CloudApiGateway implements WhatsAppGateway
{
    public function __construct(private array $config) {}

    public function sendText(string $to, string $body): string
    {
        return $this->send([
            "to" => $to,
            "type" => "text",
            "text" => ["preview_url" => false, "body" => $body],
        ]);
    }

    public function sendTemplate(string $to, string $template, string $language, array $parameters): string
    {
        $payload = [
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $template,
                "language" => ["code" => $language],
            ],
        ];

        if ($parameters) {
            $payload["template"]["components"] = [[
                "type" => "body",
                "parameters" => array_map(fn ($v) => ["type" => "text", "text" => (string) $v], array_values($parameters)),
            ]];
        }

        return $this->send($payload);
    }

    public function isLive(): bool
    {
        return true;
    }

    public function isOfficial(): bool
    {
        return true;
    }

    public function channel(): string
    {
        return "cloud";
    }

    private function send(array $payload): string
    {
        if (blank($this->config["phone_number_id"] ?? null) || blank($this->config["access_token"] ?? null)) {
            throw new WhatsAppException("WhatsApp Cloud API is not configured (WHATSAPP_PHONE_NUMBER_ID / WHATSAPP_ACCESS_TOKEN).");
        }

        $url = sprintf(
            "https://graph.facebook.com/%s/%s/messages",
            $this->config["api_version"] ?? "v21.0",
            $this->config["phone_number_id"],
        );

        try {
            $response = Http::withToken($this->config["access_token"])
                ->timeout($this->config["timeout"] ?? 15)
                ->acceptJson()
                ->post($url, ["messaging_product" => "whatsapp", "recipient_type" => "individual"] + $payload);
        } catch (ConnectionException $e) {
            throw new WhatsAppException("WhatsApp API unreachable: " . $e->getMessage(), retryable: true);
        }

        if ($response->failed()) {
            throw new WhatsAppException($this->errorMessage($response), retryable: $response->serverError() || $response->status() === 429);
        }

        $id = $response->json("messages.0.id");
        if (!$id) {
            throw new WhatsAppException("WhatsApp API returned no message id.");
        }

        return $id;
    }

    private function errorMessage(Response $response): string
    {
        $error = $response->json("error");

        return $error
            ? sprintf("[%s] %s", $error["code"] ?? $response->status(), $error["error_data"]["details"] ?? $error["message"] ?? "error")
            : "HTTP " . $response->status();
    }
}
