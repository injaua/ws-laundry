<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the business requires two-factor authentication for the user's role, nothing
 * else opens until it is set up.
 */
class RequireTwoFactor
{
    private const ALLOWED = ["profile.security", "two-factor.*", "logout", "language.switch"];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasTwoFactor() && $user->twoFactorRequired() && !$request->routeIs(...self::ALLOWED)) {
            return $request->expectsJson()
                ? response()->json(["message" => __("app.two_factor_setup_required")], 403)
                : redirect()->route("profile.security")->with("warning", __("app.two_factor_setup_required"));
        }

        return $next($request);
    }
}
