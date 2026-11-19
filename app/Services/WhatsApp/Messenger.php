<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\Customer;
use App\Models\MessageTemplate;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Single entry point for outbound WhatsApp messages. Picks the channel (staff replies
 * go through the conversation channel, automatic notifications through the official
 * one) and applies Meta's rules on the official API: free text only inside the 24h
 * customer-service window; outside it, only a template Meta has approved.
 */
class Messenger
{
    public function __construct(private WhatsAppChannels $channels) {}

    /** Free-text reply typed by staff in the inbox. */
    public function sendText(string $phone, string $body, User $sender, ?Customer $customer = null): WhatsAppMessage
    {
        $gateway = $this->replyGateway($phone);

        if (!$this->windowAllows($gateway, $phone)) {
            throw new WhatsAppException(__("app.wa_window_closed"));
        }

        return $this->queue([
            "channel" => $gateway->channel(),
            "phone" => $phone,
            "customer_id" => $customer?->id,
            "type" => "text",
            "body" => $body,
            "user_id" => $sender->id,
            "read_by_staff_at" => now(),
        ]);
    }

    /**
     * A template for a customer: the Meta-approved version when one is linked and the
     * channel is the official API (works any time), otherwise its rendered text (on the
     * official API only inside the 24h window).
     *
     * @param  array<string, scalar>  $vars  values for the template placeholders
     * @param  string|null  $notificationKey  set for automatic notifications; each key is sent once
     * @return WhatsAppMessage|null null when this notification was already sent, or notifications have no channel
     */
    public function sendTemplate(
        MessageTemplate $template,
        Customer $customer,
        array $vars,
        ?User $sender = null,
        ?Order $order = null,
        ?string $notificationKey = null,
    ): ?WhatsAppMessage {
        $phone = $customer->normalizedWhatsapp();
        if ($phone === null) {
            throw new WhatsAppException(__("app.customer_without_phone"));
        }

        // Automatic notifications never go through the unofficial QR connection.
        $gateway = $notificationKey !== null ? $this->channels->notifications() : $this->replyGateway($phone);
        if ($gateway === null) {
            return null;
        }

        $attributes = [
            "channel" => $gateway->channel(),
            "phone" => $phone,
            "customer_id" => $customer->id,
            "body" => $template->render($vars),
            "user_id" => $sender?->id,
            "order_id" => $order?->id,
            "template_id" => $template->id,
            "notification_key" => $notificationKey,
            "read_by_staff_at" => now(),
        ];

        if ($gateway->isOfficial() && $template->isApprovedOnWhatsapp()) {
            $attributes += [
                "type" => "template",
                "template_name" => $template->wa_template_name,
                "meta" => [
                    "language" => $template->wa_language ?: config("whatsapp.template_language"),
                    "params" => $template->waParameterValues($vars),
                ],
            ];
        } elseif ($this->windowAllows($gateway, $phone)) {
            $attributes["type"] = "text";
        } else {
            // Recorded as failed so the gap is visible in the inbox and the settings page.
            $attributes += ["type" => "text", "status" => "failed", "error" => __("app.wa_template_not_approved"), "status_at" => now()];

            if ($sender) {
                throw new WhatsAppException(__("app.wa_template_not_approved"));
            }
        }

        try {
            return $this->queue($attributes);
        } catch (UniqueConstraintViolationException) {
            return null; // this notification_key was already sent
        }
    }

    /** Whether staff can type a free reply to this number right now. */
    public function canSendFreeText(string $phone): bool
    {
        return $this->windowAllows($this->replyGateway($phone), $phone);
    }

    /** False in test mode (log driver): nothing reaches WhatsApp. */
    public function isLive(): bool
    {
        return $this->channels->conversations()->isLive();
    }

    /** The official API allows free text only within 24h of the customer's last message. */
    public function windowAllows(WhatsAppGateway $gateway, string $phone): bool
    {
        return !$gateway->isOfficial() || WhatsAppMessage::sessionOpen($phone, $gateway->channel());
    }

    /**
     * The channel to answer a customer on: the one they last wrote to (e.g. a reply to an
     * automatic notification on the official number), else the conversation channel.
     */
    public function replyGateway(string $phone): WhatsAppGateway
    {
        $conversations = $this->channels->conversations();
        $last = WhatsAppMessage::inbound()->where("phone", $phone)->latest("id")->value("channel");

        if ($last && $last !== $conversations->channel() && $this->channels->available($last)) {
            return $this->channels->for($last) ?? $conversations;
        }

        return $conversations;
    }

    private function queue(array $attributes): WhatsAppMessage
    {
        $message = WhatsAppMessage::create($attributes + ["direction" => WhatsAppMessage::OUT, "status" => "queued"]);

        if ($message->status === "queued") {
            SendWhatsAppMessage::dispatch($message->id)->afterCommit();
        }

        return $message;
    }
}
