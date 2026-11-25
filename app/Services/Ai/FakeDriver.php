<?php

namespace App\Services\Ai;

use App\Services\Ai\Drivers\AiDriver;
use Closure;

/** Test double installed by AiManager::fake(). */
final class FakeDriver implements AiDriver
{
    public function __construct(private Closure $handler) {}

    public function run(string $system, array $history, array $tools, callable $runTool, int $maxSteps = 8, int $maxTokens = 8000): AiResult
    {
        $result = ($this->handler)($system, $history, $tools, $runTool);

        return $result instanceof AiResult ? $result : new AiResult((string) $result, 100, 50);
    }
}
