<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Views hold markup only. JavaScript lives in resources/js (layouts, resources/js/pages/*,
 * declarative data-* behaviours in resources/js/behaviors.js) and CSS in resources/css,
 * both served by Vite. Server data reaches scripts through <x-page-data> (JSON, never executed).
 *
 * Exceptions: error pages (errors/*) stay self-contained, since they must render even when
 * the asset build is missing; style="" is fine when the value comes from data ({{ … }}).
 */
class ViewsHaveNoInlineAssetsTest extends TestCase
{
    /** Longest view allowed: split bigger ones into partials. */
    private const MAX_LINES = 300;

    private function views(): array
    {
        $root = dirname(__DIR__, 2) . '/resources/views';
        $views = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $path = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
            if (str_starts_with($path, 'errors/')) {
                continue;
            }
            $views[$path] = file_get_contents($file->getPathname());
        }

        return $views;
    }

    private function line(string $source, int $offset): int
    {
        return substr_count(substr($source, 0, $offset), "\n") + 1;
    }

    public function test_views_have_no_inline_scripts_styles_or_handlers(): void
    {
        $rules = [
            'inline <script> (move it to resources/js/pages)' => '#<script(?![^>]*\bsrc=)(?![^>]*type="application/json")[^>]*>#i',
            'external <script src> (bundle it with Vite)' => '#<script[^>]*\bsrc="(?!https://cdn\.jsdelivr\.net/npm/bootstrap@)[^"]*"#i',
            '<style> block (move it to resources/css)' => '#<style\b#i',
            'on* event handler (use a data-* behaviour)' => '#\son[a-z]+\s*=\s*["\']#i',
            'static style="" (use a class)' => '#\sstyle="(?![^"]*\{\{)[^"]*"#i',
        ];
        $violations = [];
        foreach ($this->views() as $path => $source) {
            foreach ($rules as $what => $pattern) {
                if (preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[0] as [, $offset]) {
                        $violations[] = "{$path}:" . $this->line($source, $offset) . " — {$what}";
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_views_stay_small(): void
    {
        $big = [];
        foreach ($this->views() as $path => $source) {
            $lines = substr_count($source, "\n");
            if ($lines > self::MAX_LINES) {
                $big[] = "{$path}: {$lines} lines";
            }
        }

        $this->assertSame([], $big, 'Split these views into partials (max ' . self::MAX_LINES . " lines):\n" . implode("\n", $big));
    }

    public function test_every_vite_entry_used_by_a_view_exists_and_is_built(): void
    {
        $base = dirname(__DIR__, 2);
        $config = file_get_contents($base . '/vite.config.js');
        $missing = [];
        foreach ($this->views() as $path => $source) {
            preg_match_all('#@vite\((.*?)\)\s*$#m', $source, $calls);
            foreach ($calls[1] as $args) {
                preg_match_all('#["\'](resources/[^"\']+)["\']#', $args, $entries);
                foreach ($entries[1] as $entry) {
                    $globbed = preg_match('#^resources/(js|css)/pages/[^/]+\.(js|css)$#', $entry);
                    if (!is_file($base . '/' . $entry)) {
                        $missing[] = "{$path}: {$entry} does not exist";
                    } elseif (!$globbed && !str_contains($config, "'{$entry}'")) {
                        $missing[] = "{$path}: {$entry} is not an input in vite.config.js";
                    }
                }
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }
}
