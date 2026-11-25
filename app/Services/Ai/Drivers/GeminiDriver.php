<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\AiConfig;
use App\Services\Ai\AiException;
use App\Services\Ai\AiResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Google Gemini (generateContent with function declarations). */
class GeminiDriver implements AiDriver
{
    public function __construct(private AiConfig $config) {}

    public function run(string $system, array $history, array $tools, callable $runTool, int $maxSteps = 8, int $maxTokens = 8000): AiResult
    {
        $contents = array_map(fn ($m) => [
            "role" => $m["role"] === "assistant" ? "model" : "user",
            "parts" => [["text" => $m["text"]]],
        ], $history);

        $declarations = array_map(function ($t) {
            $declaration = ["name" => $t["name"], "description" => $t["description"]];
            // Gemini refuses an object schema without properties: such tools take no parameters.
            if (!empty($t["parameters"]["properties"])) {
                $declaration["parameters"] = $t["parameters"];
            }

            return $declaration;
        }, $tools);

        $input = 0;
        $output = 0;
        $text = "";

        for ($step = 1; $step <= $maxSteps; $step++) {
            $body = [
                "systemInstruction" => ["parts" => [["text" => $system]]],
                "contents" => $contents,
                "generationConfig" => ["maxOutputTokens" => $maxTokens],
            ];
            if ($declarations) {
                $body["tools"] = [["functionDeclarations" => $declarations]];
                $body["toolConfig"] = ["functionCallingConfig" => ["mode" => $step === $maxSteps ? "NONE" : "AUTO"]];
            }

            $json = $this->post($body);
            $input += (int) data_get($json, "usageMetadata.promptTokenCount", 0);
            $output += (int) data_get($json, "usageMetadata.candidatesTokenCount", 0);

            $parts = (array) data_get($json, "candidates.0.content.parts", []);
            $text = "";
            $responses = [];
            foreach ($parts as $part) {
                if (isset($part["text"])) {
                    $text .= $part["text"];
                } elseif (isset($part["functionCall"])) {
                    $name = (string) ($part["functionCall"]["name"] ?? "");
                    $args = (array) ($part["functionCall"]["args"] ?? []);
                    $responses[] = ["functionResponse" => ["name" => $name, "response" => ["content" => $runTool($name, $args)]]];
                }
            }

            if (data_get($json, "promptFeedback.blockReason") || data_get($json, "candidates.0.finishReason") === "SAFETY") {
                return new AiResult(__("app.ai_refused"), $input, $output, refused: true, steps: $step);
            }
            if ($responses === []) {
                return new AiResult(trim($text), $input, $output, steps: $step);
            }

            $contents[] = ["role" => "model", "parts" => $parts];
            $contents[] = ["role" => "user", "parts" => $responses];
        }

        return new AiResult(trim($text), $input, $output, steps: $maxSteps);
    }

    /** @return array<string, mixed> */
    private function post(array $body): array
    {
        $url = $this->config->baseUrl . "/models/" . rawurlencode($this->config->model) . ":generateContent";

        try {
            $response = Http::timeout(120)->acceptJson()->retry(2, 1000, throw: false)
                ->withHeaders(["x-goog-api-key" => $this->config->apiKey])
                ->post($url, $body);
        } catch (ConnectionException $e) {
            throw AiException::connection($e);
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new AiException(__("app.ai_error_auth"));
        }
        if ($response->status() === 429) {
            throw new AiException(__("app.ai_error_rate_limit"));
        }
        if ($response->failed()) {
            $message = (string) (data_get($response->json(), "error.message") ?? $response->body());
            throw new AiException(__("app.ai_error_provider", ["message" => mb_substr($message, 0, 300)]));
        }

        return (array) $response->json();
    }
}
