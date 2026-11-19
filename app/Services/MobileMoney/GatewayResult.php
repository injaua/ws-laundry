<?php

namespace App\Services\MobileMoney;

/** Answer of an operator to a collect / refund / status call. */
final class GatewayResult
{
    public const SUCCESS = "success";

    public const FAILED = "failed";

    public const PENDING = "pending";

    public function __construct(
        public readonly string $status,
        public readonly ?string $providerRef = null,
        public readonly ?string $conversationId = null,
        public readonly ?string $code = null,
        public readonly ?string $message = null,
        public readonly array $raw = [],
    ) {}

    public static function success(?string $providerRef, ?string $conversationId = null, ?string $code = null, ?string $message = null, array $raw = []): self
    {
        return new self(self::SUCCESS, $providerRef, $conversationId, $code, $message, $raw);
    }

    public static function failed(?string $code, ?string $message, array $raw = []): self
    {
        return new self(self::FAILED, null, null, $code, $message, $raw);
    }

    public static function pending(?string $code = null, ?string $message = null, array $raw = [], ?string $conversationId = null): self
    {
        return new self(self::PENDING, null, $conversationId, $code, $message, $raw);
    }
}
