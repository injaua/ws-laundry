<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('lang')
            ?? session('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale', 'pt');

        if (!in_array($locale, ['pt', 'en'])) {
            $locale = config('app.locale', 'pt');
        }

        if ($request->has('lang') && in_array($request->query('lang'), ['pt', 'en'])) {
            session(['locale' => $locale]);
            Cookie::queue(Cookie::make('locale', $locale, 60 * 24 * 365));
        }

        App::setLocale($locale);

        return $next($request);
    }
}
