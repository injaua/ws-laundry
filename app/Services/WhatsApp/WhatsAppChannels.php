<?php

namespace App\Services\WhatsApp;

/**
 * Which WhatsApp channel each kind of message uses:
 *  - conversations: WHATSAPP_DRIVER (log, cloud or web — the free QR connection);
 *  - automatic notifications: WHATSAPP_NOTIFICATIONS_DRIVER, only the official
 *    Cloud API (or log in development). Sending them through the unofficial QR
 *    connection would get the number blocked, so they are turned off instead.
 */
class WhatsAppChannels
{
    public const NOTIFICATION_DRIVERS = ["cloud", "log"];

    /** @var array<string, WhatsAppGateway|null> */
    private array $gateways = [];

    public function conversations(): WhatsAppGateway
    {
        return app(WhatsAppGateway::class);
    }

    /** Null when automatic notifications have no channel and are therefore off. */
    public function notificationsDriver(): ?string
    {
        $driver = config("whatsapp.notifications_driver") ?: config("whatsapp.driver");

        return in_array($driver, self::NOTIFICATION_DRIVERS, true) ? $driver : null;
    }

    public function notifications(): ?WhatsAppGateway
    {
        $driver = $this->notificationsDriver();

        return $driver ? $this->for($driver) : null;
    }

    /** The gateway for a channel name stored on a message. */
    public function for(string $channel): ?WhatsAppGateway
    {
        $conversations = $this->conversations();

        if ($channel === $conversations->channel()) {
            return $conversations;
        }

        return $this->gateways[$channel] ??= self::make($channel);
    }

    /** Whether messages on this channel can be answered from the platform. */
    public function available(string $channel): bool
    {
        return $channel === $this->conversations()->channel() || $channel === $this->notificationsDriver();
    }

    public static function make(?string $driver): ?WhatsAppGateway
    {
        return match ($driver) {
            "cloud" => new CloudApiGateway(config("whatsapp.cloud")),
            "web" => new WebSessionGateway(config("whatsapp.web")),
            "log" => new LogGateway,
            default => null,
        };
    }
}
