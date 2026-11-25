<?php

namespace App\Services\Ai;

/** The outcome of one AI answer: its text and what it cost. */
final class AiResult
{
    public function __construct(
        public readonly string $text,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly bool $refused = false,
        public readonly int $steps = 1,
    ) {}
}
