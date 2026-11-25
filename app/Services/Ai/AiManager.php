<?php

namespace App\Services\Ai;

use App\Services\Ai\Drivers\AiDriver;
use App\Services\Ai\Drivers\AnthropicDriver;
use App\Services\Ai\Drivers\GeminiDriver;
use App\Services\Ai\Drivers\OpenAiCompatibleDriver;
use Closure;

/** Picks the driver of a configuration. Tests replace every driver with fake(). */
final class AiManager
{
    private static ?Closure $fake = null;

    public static function driver(AiConfig $config): AiDriver
    {
        if (self::$fake) {
            return new FakeDriver(self::$fake);
        }

        return match ($config->driver) {
            "anthropic" => new AnthropicDriver($config),
            "gemini" => new GeminiDriver($config),
            default => new OpenAiCompatibleDriver($config),
        };
    }

    /**
     * Every AI call runs $handler(system, history, tools, runTool) instead of a provider.
     * It returns an AiResult or a string (the answer).
     */
    public static function fake(?Closure $handler): void
    {
        self::$fake = $handler;
    }
}
