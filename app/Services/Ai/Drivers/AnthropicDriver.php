<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Beta\Messages\BetaTextBlock;
use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\Ai\AiConfig;
use App\Services\Ai\AiException;
use App\Services\Ai\AiResult;

/**
 * Anthropic (Claude) through the official PHP SDK. Adaptive thinking is the model's default;
 * effort is set explicitly where the model supports it, and the server-side fallback answers
 * when the model declines a legitimate request. The system prompt and the tools are cached.
 */
class AnthropicDriver implements AiDriver
{
    public function __construct(private AiConfig $config) {}

    public function run(string $system, array $history, array $tools, callable $runTool, int $maxSteps = 8, int $maxTokens = 8000): AiResult
    {
        $client = new Client(
            apiKey: $this->config->apiKey,
            baseUrl: $this->config->baseUrl ?: null,
            requestOptions: ["timeout" => 120.0, "maxRetries" => 2],
        );

        $messages = array_map(fn ($m) => ["role" => $m["role"], "content" => $m["text"]], $history);
        $toolParams = array_map(fn ($t) => [
            "name" => $t["name"],
            "description" => $t["description"],
            "inputSchema" => $t["parameters"],
        ], $tools);
        if ($toolParams !== []) {
            $toolParams[array_key_last($toolParams)]["cacheControl"] = ["type" => "ephemeral"];
        }

        $input = 0;
        $output = 0;
        $text = "";

        for ($step = 1; $step <= $maxSteps; $step++) {
            $last = $step === $maxSteps;
            try {
                $response = $client->beta->messages->create(
                    maxTokens: $maxTokens,
                    messages: $messages,
                    model: $this->config->model,
                    system: [["type" => "text", "text" => $system, "cacheControl" => ["type" => "ephemeral"]]],
                    tools: $toolParams ?: null,
                    toolChoice: $toolParams && $last ? ["type" => "none"] : null,
                    outputConfig: $this->supportsEffort() ? ["effort" => "medium"] : null,
                    fallbacks: "default",
                    betas: ["server-side-fallback-2026-07-01"],
                );
            } catch (AuthenticationException) {
                throw new AiException(__("app.ai_error_auth"));
            } catch (RateLimitException) {
                throw new AiException(__("app.ai_error_rate_limit"));
            } catch (APIStatusException $e) {
                throw new AiException(__("app.ai_error_provider", ["message" => mb_substr($e->getMessage(), 0, 300)]));
            } catch (APIConnectionException $e) {
                throw AiException::connection($e);
            }

            $input += $response->usage->inputTokens + (int) $response->usage->cacheReadInputTokens + (int) $response->usage->cacheCreationInputTokens;
            $output += $response->usage->outputTokens;

            $text = "";
            $results = [];
            foreach ($response->content as $block) {
                if ($block instanceof BetaTextBlock) {
                    $text .= $block->text;
                } elseif ($block instanceof BetaToolUseBlock) {
                    $results[] = ["type" => "tool_result", "toolUseID" => $block->id, "content" => $runTool($block->name, $block->input)];
                }
            }

            if ($response->stopReason === "refusal") {
                return new AiResult($text ?: __("app.ai_refused"), $input, $output, refused: true, steps: $step);
            }
            if ($response->stopReason !== "tool_use" || $results === []) {
                return new AiResult(trim($text), $input, $output, steps: $step);
            }

            // The whole assistant turn goes back (thinking blocks included), then every tool result in one user turn.
            $messages[] = ["role" => "assistant", "content" => $response->content];
            $messages[] = ["role" => "user", "content" => $results];
        }

        return new AiResult(trim($text), $input, $output, steps: $maxSteps);
    }

    /** Effort is accepted by Claude 4.5 Opus and the newer families. */
    private function supportsEffort(): bool
    {
        return (bool) preg_match('/claude-(fable|mythos|opus-5|opus-4-[5-9]|sonnet-5|haiku-5)/', $this->config->model);
    }
}
