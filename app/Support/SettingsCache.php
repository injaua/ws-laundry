<?php

namespace App\Support;

use App\Models\SiteSetting;
use Throwable;

/**
 * The settings rows of a level (null = company, or one store), read once per request / job.
 *
 * Bound as a scoped instance, so every request and every queued job starts empty; any write
 * through SiteSetting (save, delete, set, setAt, forgetAt) empties it.
 */
final class SettingsCache
{
    /** @var array<int, array<string, mixed>> level (0 = company) => key => value */
    private array $levels = [];

    public static function instance(): self
    {
        return app(self::class);
    }

    /** @return array<string, mixed> every value stored at exactly that level */
    public function level(?int $storeId): array
    {
        $slot = $storeId ?? 0;
        if (!array_key_exists($slot, $this->levels)) {
            try {
                $values = SiteSetting::query()
                    ->when($storeId, fn ($q) => $q->where("store_id", $storeId), fn ($q) => $q->whereNull("store_id"))
                    ->pluck("value", "key")->all();
            } catch (Throwable $e) {
                return []; // table not there yet (install / migrations): nothing to keep
            }
            $this->levels[$slot] = $values;
        }

        return $this->levels[$slot];
    }

    public function flush(): void
    {
        $this->levels = [];
    }
}
