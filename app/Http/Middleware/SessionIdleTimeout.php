<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Services\Security\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out a user who did nothing for the configured number of minutes (shared counter
 * computers). Background status polling (JSON) does not count as activity.
 */
class SessionIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $minutes = SecuritySettings::idleMinutes();
        $user = $request->user();

        if ($user && $minutes > 0 && $request->hasSession()) {
            $last = (int) $request->session()->get("security.last_activity", 0);

            if ($last > 0 && now()->timestamp - $last > $minutes * 60) {
                ActivityLog::security("session_expired", $user, "Session expired after {$minutes} min idle: {$user->email}", $user);
                Auth::guard("web")->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(["message" => __("app.session_idle_expired")], 401)
                    : redirect()->route("login")->with("status", __("app.session_idle_expired"));
            }

            if (!$request->expectsJson() || $last === 0) {
                $request->session()->put("security.last_activity", now()->timestamp);
            }
        }

        return $next($request);
    }
}
