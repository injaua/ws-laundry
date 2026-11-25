<?php

namespace App\Services\Ai;

use App\Support\StoreSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * AI provider configuration (settings group "ai" of StoreSettings): the company's, used by
 * every store without its own, or a store's own (set by the admin or by that store's owner).
 *
 * Any provider: Anthropic (Claude, official SDK), Google Gemini, or any service that speaks
 * the OpenAI chat-completions protocol (OpenAI, DeepSeek, Groq, Mistral, OpenRouter, a local
 * Ollama, or a custom address). The API key is stored encrypted and never sent back to the page.
 */
final class AiSettings
{
    public const KEYS = [
        "ai_enabled", "ai_provider", "ai_model", "ai_api_key", "ai_base_url",
        "ai_price_input", "ai_price_output", "ai_monthly_budget", "ai_daily_questions", "ai_mask_personal",
    ];

    /**
     * Provider => driver, default address, suggested model, whether it needs a key and the
     * price (USD per million input / output tokens) to prefill when known.
     */
    public const PROVIDERS = [
        "anthropic" => ["driver" => "anthropic", "base_url" => null, "model" => "claude-opus-5-5", "key" => true, "prices" => [4, 20]],
        "openai" => ["driver" => "openai", "base_url" => "https://api.openai.com/v1", "model" => "", "key" => true, "prices" => null],
        "gemini" => ["driver" => "gemini", "base_url" => "https://generativelanguage.googleapis.com/v1beta", "model" => "", "key" => true, "prices" => null],
        "deepseek" => ["driver" => "openai", "base_url" => "https://api.deepseek.com/v1", "model" => "", "key" => true, "prices" => null],
        "groq" => ["driver" => "openai", "base_url" => "https://api.groq.com/openai/v1", "model" => "", "key" => true, "prices" => null],
        "mistral" => ["driver" => "openai", "base_url" => "https://api.mistral.ai/v1", "model" => "", "key" => true, "prices" => null],
        "openrouter" => ["driver" => "openai", "base_url" => "https://openrouter.ai/api/v1", "model" => "", "key" => true, "prices" => null],
        "ollama" => ["driver" => "openai", "base_url" => "http://localhost:11434/v1", "model" => "", "key" => false, "prices" => [0, 0]],
        "custom" => ["driver" => "openai", "base_url" => null, "model" => "", "key" => false, "prices" => null],
    ];

    public const DEFAULT_DAILY_QUESTIONS = 30;

    /** Configuration used by a store (its own, or the company's). */
    public static function forStore(?int $storeId): AiConfig
    {
        return self::at(StoreSettings::levelFor("ai", $storeId));
    }

    /** Configuration stored at a level (null = company, a store id = that store's own). */
    public static function at(?int $level): AiConfig
    {
        $v = StoreSettings::values("ai", $level);
        $provider = array_key_exists((string) ($v["ai_provider"] ?? ""), self::PROVIDERS) ? (string) $v["ai_provider"] : "anthropic";
        $preset = self::PROVIDERS[$provider];

        $key = (string) ($v["ai_api_key"] ?? "");
        if ($key !== "") {
            try {
                $key = Crypt::decryptString($key);
            } catch (DecryptException) {
                $key = "";
            }
        }

        $number = fn (string $k) => isset($v[$k]) && $v[$k] !== "" && is_numeric($v[$k]) ? (float) $v[$k] : null;

        return new AiConfig(
            level: $level,
            enabled: (string) ($v["ai_enabled"] ?? "0") === "1",
            provider: $provider,
            driver: $preset["driver"],
            model: trim((string) ($v["ai_model"] ?? "")) ?: $preset["model"],
            apiKey: $key,
            baseUrl: rtrim(trim((string) ($v["ai_base_url"] ?? "")) ?: (string) $preset["base_url"], "/"),
            priceInput: $number("ai_price_input") ?? ($preset["prices"][0] ?? null),
            priceOutput: $number("ai_price_output") ?? ($preset["prices"][1] ?? null),
            monthlyBudget: $number("ai_monthly_budget") ?? 0.0,
            dailyQuestions: (int) ($number("ai_daily_questions") ?? self::DEFAULT_DAILY_QUESTIONS),
            maskPersonal: (string) ($v["ai_mask_personal"] ?? "1") !== "0",
            needsKey: $preset["key"],
        );
    }

    public static function hasKey(?int $level): bool
    {
        return filled(StoreSettings::values("ai", $level)["ai_api_key"] ?? null);
    }

    /** Saves at a level; an empty key keeps the one stored, "forget_key" removes it. */
    public static function save(array $data, ?int $level): void
    {
        if ($level !== null) {
            StoreSettings::takeOver("ai", $level, copyCompany: false);
        }
        $set = fn (string $key, mixed $value) => StoreSettings::set("ai", $level, $key, $value === null ? "" : (string) $value);

        $set("ai_enabled", !empty($data["enabled"]) ? "1" : "0");
        $set("ai_provider", $data["provider"]);
        $set("ai_model", trim((string) ($data["model"] ?? "")));
        $set("ai_base_url", trim((string) ($data["base_url"] ?? "")));
        $set("ai_price_input", $data["price_input"] ?? "");
        $set("ai_price_output", $data["price_output"] ?? "");
        $set("ai_monthly_budget", $data["monthly_budget"] ?? "");
        $set("ai_daily_questions", $data["daily_questions"] ?? self::DEFAULT_DAILY_QUESTIONS);
        $set("ai_mask_personal", !empty($data["mask_personal"]) ? "1" : "0");

        if (!empty($data["forget_key"])) {
            $set("ai_api_key", "");
        } elseif (filled($data["api_key"] ?? null)) {
            $set("ai_api_key", Crypt::encryptString(trim($data["api_key"])));
        }
    }
}
