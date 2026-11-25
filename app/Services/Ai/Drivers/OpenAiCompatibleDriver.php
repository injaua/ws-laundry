<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiConfig;
use App\Services\Ai\AiException;
use App\Services\Ai\AiResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Any service speaking the OpenAI protocol with function calling: OpenAI, DeepSeek, Groq,
 * Mistral, OpenRouter, a local Ollama, or a custom address.
 *
 * Chat Completions is used first, since every compatible service has it. Newer OpenAI models
 * answer only on the Responses API: when the provider says so, the run goes there instead,
 * and the model is remembered so later runs go straight to it.
 */
class OpenAiCompatibleDriver implements AiDriver
{
    /** The provider's error says this model must be called through /responses. */
    private const RESPONSES_ONLY = '/v1\/responses|responses api|only supported (in|on|via|through)|not supported (in|on|with) (the )?(v1\/)?chat|not a chat model|use the responses/i';

    /** The provider's error says the model does not take the "system" role. */
    private const NO_SYSTEM_ROLE = '/\bsystem\b.{0,40}not supported|does not support .{0,20}\bsystem\b|role.{0,40}\bsystem\b.{0,40}(unsupported|not supported)/i';

    /** The provider's error says the model cannot call tools. */
    private const NO_TOOLS = '/(tools?|function[ _]call(ing|s)?|tool_choice).{0,60}(not supported|unsupported|does not support)|(not support|unsupported).{0,40}(tools?|function[ _]call)/i';

    private string $systemRole = "system";

    public function __construct(private AiConfig $config) {}

    public function run(string $system, array $history, array $tools, callable $runTool, int $maxSteps = 8, int $maxTokens = 8000): AiResult
    {
        if (Cache::get($this->responsesKey())) {
            return $this->runResponses($system, $history, $tools, $runTool, $maxSteps);
        }

        try {
            return $this->runChat($system, $history, $tools, $runTool, $maxSteps);
        } catch (UseResponsesApi) {
            Cache::forever($this->responsesKey(), true);

            return $this->runResponses($system, $history, $tools, $runTool, $maxSteps);
        }
    }

    private function runChat(string $system, array $history, array $tools, callable $runTool, int $maxSteps): AiResult
    {
        $messages = [["role" => $this->systemRole, "content" => $system]];
        foreach ($history as $m) {
            $messages[] = ["role" => $m["role"], "content" => $m["text"]];
        }
        $functions = array_map(fn ($t) => [
            "type" => "function",
            "function" => ["name" => $t["name"], "description" => $t["description"], "parameters" => $t["parameters"]],
        ], $tools);

        $input = 0;
        $output = 0;
        $text = "";

        for ($step = 1; $step <= $maxSteps; $step++) {
            $messages[0]["role"] = $this->systemRole;
            $body = ["model" => $this->config->model, "messages" => $messages];
            if ($functions) {
                $body["tools"] = $functions;
                $body["tool_choice"] = $step === $maxSteps ? "none" : "auto";
            }

            $json = $this->post("/chat/completions", $body, chat: true);
            $input += (int) data_get($json, "usage.prompt_tokens", 0);
            $output += (int) data_get($json, "usage.completion_tokens", 0);

            $message = (array) data_get($json, "choices.0.message", []);
            $text = (string) ($message["content"] ?? "");
            $calls = (array) ($message["tool_calls"] ?? []);
            if ($calls === []) {
                $refused = filled($message["refusal"] ?? null);

                return new AiResult(trim($refused ? (string) $message["refusal"] : $text), $input, $output, $refused, $step);
            }

            $messages[] = ["role" => "assistant", "content" => $message["content"] ?? null, "tool_calls" => $calls];
            foreach ($calls as $call) {
                $arguments = json_decode((string) data_get($call, "function.arguments", "{}"), true);
                $messages[] = [
                    "role" => "tool",
                    "tool_call_id" => (string) data_get($call, "id", ""),
                    "content" => $runTool((string) data_get($call, "function.name", ""), is_array($arguments) ? $arguments : []),
                ];
            }
        }

        return new AiResult(trim($text), $input, $output, steps: $maxSteps);
    }

    /** The same loop on the Responses API (POST /responses), for the models that only answer there. */
    private function runResponses(string $system, array $history, array $tools, callable $runTool, int $maxSteps): AiResult
    {
        $items = [];
        foreach ($history as $m) {
            $items[] = ["role" => $m["role"], "content" => $m["text"]];
        }
        $functions = array_map(fn ($t) => [
            "type" => "function",
            "name" => $t["name"],
            "description" => $t["description"],
            "parameters" => $t["parameters"],
        ], $tools);

        $input = 0;
        $output = 0;
        $text = "";

        for ($step = 1; $step <= $maxSteps; $step++) {
            $body = ["model" => $this->config->model, "instructions" => $system, "input" => $items];
            if ($functions) {
                $body["tools"] = $functions;
                $body["tool_choice"] = $step === $maxSteps ? "none" : "auto";
            }

            $json = $this->post("/responses", $body, chat: false);
            $input += (int) data_get($json, "usage.input_tokens", 0);
            $output += (int) data_get($json, "usage.output_tokens", 0);

            $text = "";
            $refusal = "";
            $calls = [];
            foreach ((array) ($json["output"] ?? []) as $item) {
                $type = $item["type"] ?? "";
                if ($type === "function_call") {
                    $calls[] = $item;
                } elseif ($type === "message") {
                    foreach ((array) ($item["content"] ?? []) as $part) {
                        if (($part["type"] ?? "") === "output_text") {
                            $text .= (string) ($part["text"] ?? "");
                        } elseif (($part["type"] ?? "") === "refusal") {
                            $refusal .= (string) ($part["refusal"] ?? "");
                        }
                    }
                }
            }

            if ($calls === []) {
                $refused = $refusal !== "" && trim($text) === "";

                return new AiResult(trim($refused ? $refusal : $text), $input, $output, $refused, $step);
            }

            // The whole output goes back (reasoning items included) so the model keeps its thread.
            foreach ((array) $json["output"] as $item) {
                $items[] = $item;
            }
            foreach ($calls as $call) {
                $arguments = json_decode((string) ($call["arguments"] ?? "{}"), true);
                $items[] = [
                    "type" => "function_call_output",
                    "call_id" => (string) ($call["call_id"] ?? ""),
                    "output" => (string) $runTool((string) ($call["name"] ?? ""), is_array($arguments) ? $arguments : []),
                ];
            }
        }

        return new AiResult(trim($text), $input, $output, steps: $maxSteps);
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $body, bool $chat): array
    {
        $response = $this->send($path, $body);

        // Some reasoning models refuse the "system" role and want "developer" instead.
        if ($chat && $response->status() === 400 && $this->systemRole === "system" && preg_match(self::NO_SYSTEM_ROLE, $this->errorOf($response))) {
            $this->systemRole = "developer";
            $body["messages"][0]["role"] = "developer";
            $response = $this->send($path, $body);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new AiException(__("app.ai_error_auth"));
        }
        if ($response->status() === 429) {
            throw new AiException(__("app.ai_error_rate_limit"));
        }
        if ($response->failed()) {
            $message = $this->errorOf($response);
            if ($chat && in_array($response->status(), [400, 404], true) && preg_match(self::RESPONSES_ONLY, $message)) {
                throw new UseResponsesApi();
            }
            if (isset($body["tools"]) && $response->status() === 400 && preg_match(self::NO_TOOLS, $message)) {
                throw new AiException(__("app.ai_error_no_tools", ["model" => $this->config->model]));
            }
            throw new AiException(__("app.ai_error_provider", ["message" => mb_substr($message, 0, 300)]));
        }

        return (array) $response->json();
    }

    private function send(string $path, array $body): Response
    {
        $request = Http::timeout(120)->acceptJson()->retry(2, 1000, fn ($e) => !$e instanceof RequestException || $e->response->serverError(), throw: false);
        if ($this->config->apiKey !== "") {
            $request = $request->withToken($this->config->apiKey);
        }

        try {
            return $request->post($this->config->baseUrl . $path, $body);
        } catch (ConnectionException $e) {
            throw AiException::connection($e);
        }
    }

    private function errorOf(Response $response): string
    {
        return (string) (data_get($response->json(), "error.message") ?? $response->body());
    }

    private function responsesKey(): string
    {
        return "ai.responses_only." . md5($this->config->baseUrl . "|" . $this->config->model);
    }
}
