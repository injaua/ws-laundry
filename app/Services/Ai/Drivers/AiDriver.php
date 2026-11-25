<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiResult;

/**
 * One AI provider. run() holds the whole exchange of one answer in the provider's own format:
 * it sends the conversation and the tools, runs every tool the model asks for through
 * $runTool, and repeats until the model answers (or the step limit is reached).
 */
interface AiDriver
{
    /**
     * @param  list<array{role: string, text: string}>  $history  earlier turns and the new question (last)
     * @param  list<array{name: string, description: string, parameters: array<string, mixed>}>  $tools
     * @param  callable(string, array<string, mixed>): string  $runTool  name, input => JSON result
     */
    public function run(string $system, array $history, array $tools, callable $runTool, int $maxSteps = 8, int $maxTokens = 8000): AiResult;
}
