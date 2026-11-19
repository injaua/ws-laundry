<?php

namespace App\Services\WhatsApp;

/**
 * Which WhatsApp channel each kind of message uses, in the store worked in (its own WhatsApp
 * configuration, or the company's):
 *  - conversations: log, cloud or web (the free QR connection);
 *  - automatic notifications: only the official Cloud API (or log in development).
 *    Sending them through the unofficial QR connection would get the number blocked,
 *    so they are turned off instead.
 */
class WhatsAppChannels
{
    public const NOTIFICATION_DRIVERS = ["cloud", "log"];

    /** Configuration of the store worked in (see WhatsAppSettings::for). */
    public function settings(): array
    {
        return WhatsAppSettings::current();
    }

    public function conversations(): WhatsAppGateway
    {
        return app(WhatsAppGateway::class);
    }

    /** The conversation gateway of the store worked in (the container binding of WhatsAppGateway). */
    public function conversationGateway(): WhatsAppGateway
    {
        $settings = $this->settings();

        return self::make($settings["driver"] ?? null, $settings) ?? new LogGateway;
    }

    /** Null when automatic notifications have no channel and are therefore off. */
    public function notificationsDriver(): ?string
    {
        $settings = $this->settings();
        $driver = ($settings["notifications_driver"] ?? null) ?: ($settings["driver"] ?? null);

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

        return self::make($channel, $this->settings());
    }

    /** Whether messages on this channel can be answered from the platform. */
    public function available(string $channel): bool
    {
        return $channel === $this->conversations()->channel() || $channel === $this->notificationsDriver();
    }

    /** @param  array|null  $settings  a WhatsAppSettings configuration; null = the company's (config/whatsapp.php) */
    public static function make(?string $driver, ?array $settings = null): ?WhatsAppGateway
    {
        $settings ??= (array) config("whatsapp");

        return match ($driver) {
            "cloud" => new CloudApiGateway((array) ($settings["cloud"] ?? [])),
            "web" => new WebSessionGateway((array) ($settings["web"] ?? [])),
            "log" => new LogGateway,
            default => null,
        };
    }
}
