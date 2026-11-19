<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    /** Errors worth retrying (network, rate limit, Meta 5xx) as opposed to a rejected message. */
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
