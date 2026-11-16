<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class LanguageController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        if (!in_array($locale, ['pt', 'en'])) {
            $locale = 'pt';
        }

        session(['locale' => $locale]);
        session()->save();

        $cookie = Cookie::make('locale', $locale, 60 * 24 * 365, null, null, false, false);
        Cookie::queue($cookie);

        $previous = url()->previous();
        if (!$previous || $previous === $request->fullUrl() || str_contains($previous, '/language/')) {
            $previous = auth()->check() ? route('dashboard') : route('home');
        }

        return redirect()->to($previous)->withCookie($cookie);
    }
}
