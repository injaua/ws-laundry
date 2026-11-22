<?php

namespace App\Http\Middleware;

use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies Admin → System settings once the request knows its user, hence its store.
 *
 * The service provider boots before the session and the user are known, so whatever it reads
 * is the company / main store view. Here, after StartSession:
 *  - uploaded files (logos, favicon, photos) get the host the browser is using, not
 *    APP_URL ("php artisan serve", another port or domain would break every image);
 *  - config("app.name") and the time zone follow the store being worked in.
 */
class ApplySystemSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        // Set before the disk is first built (nothing reads files earlier in a request).
        config(["filesystems.disks.public.url" => rtrim($request->root(), "/") . "/storage"]);

        SystemSettings::flush();
        SystemSettings::apply();

        return $next($request);
    }
}
