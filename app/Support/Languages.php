<?php

namespace App\Support;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Languages the administrator manages (Admin → System settings → Languages). Read once per
 * request; translated texts are cached until they are edited.
 */
class Languages
{
    /** All languages (active or not), keyed by code; read once per request / job. */
    public static function all(): Collection
    {
        return RequestCache::instance()->remember("languages:all", function () {
            try {
                $all = Language::orderByDesc("is_system")->orderBy("name")->get()->keyBy("code");
            } catch (Throwable) {
                $all = collect(); // table not migrated yet
            }
            if ($all->isEmpty()) {
                $all = collect([
                    "pt" => new Language(["code" => "pt", "name" => "Português", "flag" => "🇵🇹", "direction" => "ltr", "base_locale" => "pt", "is_active" => true, "is_system" => true]),
                    "en" => new Language(["code" => "en", "name" => "English", "flag" => "🇬🇧", "direction" => "ltr", "base_locale" => "en", "is_active" => true, "is_system" => true]),
                ]);
            }

            return $all;
        });
    }

    /** Languages users can switch to. */
    public static function active(): Collection
    {
        return self::all()->where("is_active", true);
    }

    public static function isActive(?string $code): bool
    {
        return $code !== null && self::active()->has($code);
    }

    public static function find(string $code): ?Language
    {
        return self::all()->get($code);
    }

    public static function isRtl(string $code): bool
    {
        return (bool) self::find($code)?->isRtl();
    }

    /** The lang/ files a language reads (itself for pt / en, its base for new ones). */
    public static function baseOf(string $code): string
    {
        $language = self::find($code);

        return $language && in_array($language->base_locale, Language::BASES, true) ? $language->base_locale : "en";
    }

    /** Edited / translated texts of one group: [dot.key => text]. */
    public static function overrides(string $locale, string $group): array
    {
        try {
            return Cache::rememberForever(self::cacheKey($locale, $group), fn () => Translation::where("locale", $locale)->where("group", $group)->pluck("value", "key")->all());
        } catch (Throwable) {
            return [];
        }
    }

    /** The base texts of a group as [dot.key => text] (what the editor lists). */
    public static function baseLines(string $locale, string $group): array
    {
        $lines = self::files()->load(self::baseOf($locale), $group, "*");

        return array_filter(Arr::dot($lines), fn ($v) => is_string($v));
    }

    /** Lines as the language shows them now (base + overrides). */
    public static function currentLines(string $locale, string $group): array
    {
        return array_merge(self::baseLines($locale, $group), self::overrides($locale, $group));
    }

    public static function flush(?string $locale = null): void
    {
        RequestCache::instance()->forget("languages:");
        foreach ($locale ? [$locale] : self::all()->keys()->all() as $code) {
            foreach (Language::GROUPS as $group) {
                Cache::forget(self::cacheKey($code, $group));
            }
        }
        RequestCache::instance()->forget("languages:");
        if (app()->resolved("translator")) {
            app("translator")->setLoaded([]); // reload texts in this request too
        }
    }

    /** The plain lang/ file loader (without the administrator's translations). */
    private static function files(): Loader
    {
        app("translator"); // makes sure the loaders are built

        return app("translation.loader.files");
    }

    private static function cacheKey(string $locale, string $group): string
    {
        return "translations.{$locale}.{$group}";
    }
}
