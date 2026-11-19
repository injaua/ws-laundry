<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections on every response: no framing by other sites (clickjacking), no
 * MIME sniffing, a limited referrer, no camera / microphone / location, and HSTS when the
 * site is served over HTTPS.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set("X-Frame-Options", "SAMEORIGIN");
        $headers->set("X-Content-Type-Options", "nosniff");
        $headers->set("Referrer-Policy", "strict-origin-when-cross-origin");
        $headers->set("Permissions-Policy", "camera=(), microphone=(), geolocation=(), payment=()");
        $headers->set("Cross-Origin-Opener-Policy", "same-origin");
        $headers->remove("X-Powered-By");

        if ($request->isSecure()) {
            $headers->set("Strict-Transport-Security", "max-age=31536000; includeSubDomains");
        }

        // Pages with personal data must not be kept by shared browsers / proxies.
        if ($request->user() && !$headers->has("Content-Disposition")) {
            $headers->set("Cache-Control", "no-store, private");
        }

        return $response;
    }
}
