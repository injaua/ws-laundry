<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Views only present data: queries and service calls belong in controllers,
 * view composers (app/View/Composers) or models. Pure static helpers
 * (e.g. Cheque::colorFor, Payment::isMobile) and settings reads are allowed.
 */
class ViewsHaveNoQueriesTest extends TestCase
{
    private const FORBIDDEN = [
        "Eloquent query on a model" => '#App/Models/\w+::(where\w*|query|find\w*|first\w*|all|count|sum|avg|max|min|latest|oldest|orderBy|with|withoutGlobalScopes?|pluck|exists|select|active|pending|late|unread|currentFor)\(#',
        "DB facade" => '#\bDB::\w+\(#',
        "query on a relation" => '#(?<!->filter|->reject)->\w+\(\)->(where\w*|orderBy|latest|oldest|limit|with|sum|count|exists|get|first|pluck)\(#',
        "service resolved from the container" => '#app\(\s*/?App/(Services|Actions)/#',
    ];

    public function test_views_do_not_query_the_database(): void
    {
        $root = dirname(__DIR__, 2) . "/resources/views";
        $violations = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if (!str_ends_with($file->getFilename(), ".blade.php")) {
                continue;
            }
            foreach (file($file->getPathname()) as $i => $line) {
                $normalized = str_replace("\\", "/", $line);
                foreach (self::FORBIDDEN as $what => $pattern) {
                    if (preg_match($pattern, $normalized)) {
                        $path = substr(str_replace("\\", "/", $file->getPathname()), strlen($root) + 1);
                        $violations[] = "{$path}:" . ($i + 1) . " — {$what}";
                    }
                }
            }
        }

        $this->assertSame([], $violations, "Move this logic to the controller or a view composer:\n" . implode("\n", $violations));
    }
}
