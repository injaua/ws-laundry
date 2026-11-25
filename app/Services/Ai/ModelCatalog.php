<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Models a provider offers: a short list of suggestions known in advance, and the live list
 * asked from the provider with the key (Anthropic, Gemini and every OpenAI-compatible API
 * publish one). The model field stays free text: any model the provider has can be used.
 */
class ModelCatalog
{
    /** Suggestions shown before (or without) asking the provider. */
    public const SUGGESTED = [
        "anthropic" => ["claude-opus-5-5", "claude-sonnet-5-5", "claude-haiku-5-5", "claude-fable-5-2"],
        "openai" => ["gpt-5", "gpt-5-mini", "gpt-4.1", "gpt-4.1-mini"],
        "gemini" => ["gemini-2.5-pro", "gemini-2.5-flash", "gemini-2.5-flash-lite"],
        "deepseek" => ["deepseek-chat", "deepseek-reasoner"],
        "groq" => ["llama-3.3-70b-versatile", "openai/gpt-oss-120b", "qwen/qwen3-32b"],
        "mistral" => ["mistral-large-latest", "mistral-medium-latest", "mistral-small-latest"],
        "openrouter" => ["anthropic/claude-opus-5-5", "openai/gpt-5", "google/gemini-2.5-pro", "deepseek/deepseek-chat"],
        "ollama" => ["llama3.1", "qwen2.5", "mistral-nemo"],
        "custom" => [],
    ];

    /** @return array<string, string[]> */
    public static function suggestions(): array
    {
        return self::SUGGESTED;
    }

    /**
     * The models the provider lists for this key, sorted.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AiException
     */
    public function live(string $provider, string $apiKey, string $baseUrl): array
    {
        $driver = AiSettings::PROVIDERS[$provider]["driver"] ?? "openai";
        $baseUrl = rtrim($baseUrl ?: (string) (AiSettings::PROVIDERS[$provider]["base_url"] ?? ""), "/");

        $models = match ($driver) {
            "anthropic" => $this->anthropic($apiKey, $baseUrl),
            "gemini" => $this->gemini($apiKey, $baseUrl),
            default => $this->openAi($apiKey, $baseUrl),
        };

        usort($models, fn ($a, $b) => strcmp($a["id"], $b["id"]));

        return array_values(array_unique($models, SORT_REGULAR));
    }

    /** @return list<array{id: string, name: string}> */
    private function anthropic(string $apiKey, string $baseUrl): array
    {
        if ($apiKey === "") {
            throw new AiException(__("app.ai_models_need_key"));
        }
        try {
            $client = new Client(apiKey: $apiKey, baseUrl: $baseUrl ?: null, requestOptions: ["timeout" => 20.0, "maxRetries" => 1]);
            $items = $client->models->list(limit: 100)->getItems();
        } catch (APIConnectionException $e) {
            throw AiException::connection($e);
        } catch (Throwable $e) {
            throw new AiException(__("app.ai_models_failed", ["message" => mb_substr($e->getMessage(), 0, 200)]));
        }

        return array_map(fn ($m) => ["id" => $m->id, "name" => $m->displayName ?: $m->id], $items);
    }

    /** @return list<array{id: string, name: string}> */
    private function gemini(string $apiKey, string $baseUrl): array
    {
        if ($apiKey === "") {
            throw new AiException(__("app.ai_models_need_key"));
        }
        $json = $this->get($baseUrl . "/models?pageSize=200", ["x-goog-api-key" => $apiKey]);
        $models = [];
        foreach ((array) ($json["models"] ?? []) as $m) {
            // Only the models that answer messages (not embeddings, image or audio ones).
            if (!in_array("generateContent", (array) ($m["supportedGenerationMethods"] ?? []), true)) {
                continue;
            }
            $id = preg_replace('#^models/#', "", (string) ($m["name"] ?? ""));
            if ($id !== "") {
                $models[] = ["id" => $id, "name" => (string) ($m["displayName"] ?? $id)];
            }
        }

        return $models;
    }

    /** @return list<array{id: string, name: string}> */
    private function openAi(string $apiKey, string $baseUrl): array
    {
        if ($baseUrl === "") {
            throw new AiException(__("app.ai_base_url_required"));
        }
        $json = $this->get($baseUrl . "/models", $apiKey !== "" ? ["Authorization" => "Bearer " . $apiKey] : []);
        $models = [];
        foreach ((array) ($json["data"] ?? []) as $m) {
            $id = (string) ($m["id"] ?? "");
            // Leave out what cannot chat: embeddings, speech, images, moderation.
            if ($id === "" || preg_match('/embed|whisper|tts|dall-e|image|moderation|transcribe|audio|realtime/i', $id)) {
                continue;
            }
            $models[] = ["id" => $id, "name" => (string) ($m["name"] ?? $id)];
        }

        return $models;
    }

    /** @return array<string, mixed> */
    private function get(string $url, array $headers): array
    {
        try {
            $response = Http::timeout(20)->acceptJson()->withHeaders($headers)->get($url);
        } catch (ConnectionException $e) {
            throw AiException::connection($e);
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new AiException(__("app.ai_error_auth"));
        }
        if ($response->failed()) {
            $message = (string) (data_get($response->json(), "error.message") ?? $response->status());
            throw new AiException(__("app.ai_models_failed", ["message" => mb_substr($message, 0, 200)]));
        }

        return (array) $response->json();
    }
}
