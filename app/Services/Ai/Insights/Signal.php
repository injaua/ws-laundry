<?php

namespace App\Services\Ai\Insights;

/** Something a detector found, with a ready-made text (used when the AI is not available). */
final class Signal
{
    public function __construct(
        public readonly string $detector,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $body,
        public readonly string $recommendation,
        public readonly array $data,
        public readonly ?string $link,
        public readonly string $fingerprint,
    ) {}
}
