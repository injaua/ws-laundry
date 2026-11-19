<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppChannels;
use App\Services\WhatsApp\WhatsAppException;
use App\Support\CurrentStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hands one queued outbound message to the provider and records the outcome. */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public int $messageId) {}

    /** Wait 1, 5 and 15 minutes between attempts for network/rate-limit errors. */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(WhatsAppChannels $channels): void
    {
        // Queue workers have no store: work in the store of the message.
        $message = WhatsAppMessage::acrossStores()->find($this->messageId);
        if ($message) {
            CurrentStore::run($message->store_id, fn () => $this->send($message, $channels));
        }
    }

    private function send(WhatsAppMessage $message, WhatsAppChannels $channels): void
    {

        if (!$message || $message->status !== "queued") {
            return; // already sent, or deleted
        }

        // Sent through the channel it was queued for, even if the settings changed since.
        $gateway = $channels->for($message->channel ?? $channels->conversations()->channel());
        if ($gateway === null) {
            $message->update(["status" => "failed", "error" => __("app.wa_channel_unavailable"), "status_at" => now()]);

            return;
        }

        try {
            $providerId = $message->type === "template"
                ? $gateway->sendTemplate(
                    $message->phone,
                    $message->template_name,
                    $message->meta["language"] ?? config("whatsapp.template_language"),
                    $message->meta["params"] ?? [],
                )
                : $gateway->sendText($message->phone, (string) $message->body);
        } catch (WhatsAppException $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $message->update(["error" => $e->getMessage()]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 900);

                return;
            }

            $message->update(["status" => "failed", "error" => mb_substr($e->getMessage(), 0, 500), "status_at" => now()]);

            return;
        }

        $message->update([
            "status" => "sent",
            "provider_message_id" => $providerId,
            "error" => null,
            "status_at" => now(),
        ]);
    }
}
