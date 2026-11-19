<?php

namespace App\Http\Middleware;

use App\Support\Languages;
use App\Support\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('lang')
            ?? session('locale')
            ?? $request->cookie('locale')
            ?? SystemSettings::defaultLocale();

        if (!Languages::isActive($locale)) {
            $locale = SystemSettings::defaultLocale();
        }

        if ($request->has('lang') && Languages::isActive($request->query('lang'))) {
            session(['locale' => $locale]);
            Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365));
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
