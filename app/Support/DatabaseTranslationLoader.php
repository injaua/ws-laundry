<?php

namespace App\Support;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;

/**
 * Texts come from lang/ files and the administrator's translations on top. A language
 * created in the admin has no files: it starts with its base language's texts.
 */
class DatabaseTranslationLoader implements Loader
{
    public function __construct(private Loader $files) {}

    public function load($locale, $group, $namespace = null)
    {
        // Package namespaces and JSON files are untouched.
        if (($namespace !== null && $namespace !== "*") || $group === "*") {
            return $this->files->load($locale, $group, $namespace);
        }

        $lines = $this->files->load($locale, $group, $namespace);
        if ($lines === [] && ($base = Languages::baseOf($locale)) !== $locale) {
            $lines = $this->files->load($base, $group, $namespace);
        }

        foreach (Languages::overrides($locale, $group) as $key => $value) {
            Arr::set($lines, $key, $value);
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint)
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->files->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->files->namespaces();
    }
}
