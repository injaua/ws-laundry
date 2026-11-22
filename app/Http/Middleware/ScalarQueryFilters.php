<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The list filters (search, status, dates, page…) are always single values. A link such as
 * ?search[]=x or ?status[]=a would otherwise reach "like %{$search}%", Carbon or a view echo
 * as an array and crash the page with a 500. On GET requests these keys are reduced to their
 * first scalar value (or dropped). Keys that legitimately carry arrays are not listed here.
 */
class ScalarQueryFilters
{
    public const KEYS = [
        "search", "q", "status", "category", "type", "kind", "source", "method", "role", "period",
        "month", "year", "page", "per_page", "sort", "direction", "format", "tab", "filter",
        "from", "to", "date", "period_start", "period_end",
        "customer_id", "account_id", "employee_id", "courier", "store", "locale",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod("GET") || $request->isMethod("HEAD")) {
            foreach (self::KEYS as $key) {
                $value = $request->query->all()[$key] ?? null;
                if (is_array($value)) {
                    $first = collect($value)->flatten()->first(fn ($v) => is_scalar($v));
                    $first === null ? $request->query->remove($key) : $request->query->set($key, (string) $first);
                }
            }
        }

        return $next($request);
    }
}
