<?php

use App\Http\Middleware\ApplySystemSettings;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PublicStore;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionIdleTimeout;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // After StartSession: the user, hence the store, is known from here on.
            ApplySystemSettings::class,
            SetLocale::class,
            EnsureUserIsActive::class,
            SessionIdleTimeout::class,
            RequireTwoFactor::class,
        ]);
        // Browser protections on every response, including the public pages.
        $middleware->append(SecurityHeaders::class);
        // Meta signs webhook calls (X-Hub-Signature-256) instead of sending a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/whatsapp', 'webhooks/whatsapp-web']);
        $middleware->alias([
            'role' => CheckRole::class,
            'store.public' => PublicStore::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
