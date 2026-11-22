<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\CurrentStore;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages (website, payment links) have no logged-in user, hence no store. They run in:
 *  - the store of the record in the URL (a signed payment link of an order or transaction);
 *  - else the store the visitor picked (?store= / form field), when it is active;
 *  - else the main store (the first active one).
 * Records created there (pickup requests, payment requests) are stamped with that store.
 */
class PublicStore
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model && $parameter->getAttribute("store_id")) {
                return $this->runIn((int) $parameter->getAttribute("store_id"), $request, $next);
            }
        }

        $chosen = $request->integer("store");
        $store = ($chosen ? Store::active()->find($chosen) : null)
            ?? Store::active()->orderBy("id")->first();

        return $this->runIn($store?->id ?? Store::defaultId(), $request, $next);
    }

    /** Runs the request in that store, with its name / settings applied (ApplySystemSettings). */
    private function runIn(?int $storeId, Request $request, Closure $next): Response
    {
        return CurrentStore::run($storeId, function () use ($request, $next) {
            SystemSettings::apply();

            return $next($request);
        });
    }
}
