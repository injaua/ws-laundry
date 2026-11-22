<?php

namespace App\Support;

use Closure;

/**
 * Values computed once per request / queued job: current store lookups, the main store id,
 * system settings, languages.
 *
 * Bound as a scoped instance (AppServiceProvider), so the container drops it between
 * requests (Octane), between queued jobs and between tests: nothing computed for one
 * context can leak into the next, unlike static properties.
 */
final class RequestCache
{
    /** @var array<string, mixed> */
    private array $items = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->has($key) ? $this->items[$key] : $default;
    }

    public function put(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    /** The cached value, computed by $callback the first time (null is cached too). */
    public function remember(string $key, Closure $callback): mixed
    {
        if (!$this->has($key)) {
            $this->items[$key] = $callback();
        }

        return $this->items[$key];
    }

    /** Forgets every key starting with one of the prefixes ("system-settings:"…). */
    public function forget(string ...$prefixes): void
    {
        foreach (array_keys($this->items) as $key) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    unset($this->items[$key]);
                    break;
                }
            }
        }
    }

    public function flush(): void
    {
        $this->items = [];
    }
}
