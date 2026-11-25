<?php

namespace App\Services\Ai;

/** The AI configuration resolved for a level (see AiSettings). */
final class AiConfig
{
    public function __construct(
        public readonly ?int $level,
        public readonly bool $enabled,
        public readonly string $provider,
        public readonly string $driver,
        public readonly string $model,
        public readonly string $apiKey,
        public readonly string $baseUrl,
        public readonly ?float $priceInput,
        public readonly ?float $priceOutput,
        public readonly float $monthlyBudget,
        public readonly int $dailyQuestions,
        public readonly bool $maskPersonal,
        public readonly bool $needsKey,
    ) {}

    /** Everything needed to call the provider is filled in. */
    public function complete(): bool
    {
        return $this->model !== ""
            && (!$this->needsKey || $this->apiKey !== "")
            && ($this->driver === "anthropic" || $this->baseUrl !== "");
    }

    /** @return string[] what is still to be filled in: "enabled", "model", "api_key", "base_url" */
    public function missing(): array
    {
        return array_keys(array_filter([
            "enabled" => !$this->enabled,
            "model" => $this->model === "",
            "api_key" => $this->needsKey && $this->apiKey === "",
            "base_url" => $this->driver !== "anthropic" && $this->baseUrl === "",
        ]));
    }

    /** Switched on and complete: the assistant and the AI insights can run. */
    public function usable(): bool
    {
        return $this->enabled && $this->complete();
    }

    /** Cost in USD of a call, when the prices are known. */
    public function cost(int $inputTokens, int $outputTokens): float
    {
        if ($this->priceInput === null || $this->priceOutput === null) {
            return 0.0;
        }

        return round(($inputTokens * $this->priceInput + $outputTokens * $this->priceOutput) / 1_000_000, 6);
    }
}
