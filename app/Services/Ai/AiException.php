<?php

namespace App\Services\Ai;

use RuntimeException;
use Throwable;

/** The AI could not answer: not configured, over a limit, or the provider failed. The message is shown to the user. */
class AiException extends RuntimeException
{
    /** The provider could not be reached: says why when the cause is known (certificates, name, timeout). */
    public static function connection(Throwable $e): self
    {
        $detail = $e->getMessage() . " " . ($e->getPrevious()?->getMessage() ?? "");

        $key = match (true) {
            (bool) preg_match('/cURL error (60|77)|SSL certificate|certificate verify|issuer certificate/i', $detail) => "app.ai_error_ssl",
            (bool) preg_match('/cURL error 6\b|Could not resolve host/i', $detail) => "app.ai_error_dns",
            (bool) preg_match('/cURL error 28|timed out/i', $detail) => "app.ai_error_timeout",
            default => "app.ai_error_connection",
        };

        return new self(__($key), 0, $e);
    }
}
