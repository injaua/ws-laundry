<?php

namespace App\Services\WhatsApp;

/**
 * Sends a WhatsApp message through a provider and returns the provider's message id.
 * Throws WhatsAppException when the provider rejects the message.
 */
interface WhatsAppGateway
{
    public function sendText(string $to, string $body): string;

    /** @param  string[]  $parameters  values for the template's {{1}}, {{2}}… */
    public function sendTemplate(string $to, string $template, string $language, array $parameters): string;

    /** False for the log driver: nothing leaves the platform. */
    public function isLive(): bool;

    /**
     * True for Meta's official Cloud API, which only allows free text within 24h of the
     * customer's last message and supports approved templates.
     */
    public function isOfficial(): bool;

    /** Stored on each message so it is sent, and replied to, through the same channel. */
    public function channel(): string;
}
