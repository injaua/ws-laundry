<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppChannels;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\DatabaseTranslationLoader;
use App\Support\Permissions;
use App\Support\SettingsCache;
use App\Support\StorePresenceVerifier;
use App\Support\SystemSettings;
use App\View\Composers\AdminNavigationComposer;
use App\View\Composers\DeliveryFieldsComposer;
use App\View\Composers\MobileChargeComposer;
use App\View\Composers\PieceTrackingComposer;
use App\View\Composers\StoreSwitcherComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // exists / unique rules on store-owned tables only look inside the current store. extend()
        // (not singleton) so the binding survives the deferred ValidationServiceProvider.
        $this->app->extend("validation.presence", fn ($verifier, $app) => new StorePresenceVerifier($app["db"]));

        // The channel staff conversations go through (log, cloud or web), in the store worked in:
        // resolved on each use, since a store may have its own WhatsApp configuration.
        $this->app->bind(WhatsAppGateway::class, fn ($app) => $app->make(WhatsAppChannels::class)->conversationGateway());
        $this->app->singleton(WhatsAppChannels::class);
        // Settings rows, read once per request / queued job (reset between them).
        $this->app->scoped(SettingsCache::class);
        // One count of the sidebar badges per request, however many times the layout renders.
        $this->app->singleton(AdminNavigationComposer::class);

        // Texts: lang/ files + translations made in Admin → System settings → Languages.
        $this->app->extend("translation.loader", function ($loader, $app) {
            $app->instance("translation.loader.files", $loader);

            return new DatabaseTranslationLoader($loader);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's default links() markup is Tailwind; this app only loads Bootstrap 5.
        Paginator::useBootstrapFive();

        // Who may do what (routes "can:…", @can in the views): App\Support\Permissions.
        Permissions::register();

        // Uploaded files (logos, favicon, photos) are served from the host the browser is
        // using, not APP_URL: with "php artisan serve" or a different port / domain the
        // configured URL points elsewhere and every image breaks.
        if (!$this->app->runningInConsole()) {
            config(["filesystems.disks.public.url" => rtrim(request()->root(), "/") . "/storage"]);
        }

        // Admin → System settings: company name, time zone (read fresh on every boot).
        SystemSettings::flush();
        SystemSettings::apply();
        WhatsAppSettings::apply();
        View::share("sys", new SystemSettings);

        // Data that shared views / partials need, kept out of the Blade files.
        View::composer("layouts.admin.sidebar", AdminNavigationComposer::class);
        View::composer("layouts.admin.store-switcher", StoreSwitcherComposer::class);
        View::composer("mobile-payments._charge", MobileChargeComposer::class);
        View::composer("orders._pieces", PieceTrackingComposer::class);
        View::composer("customers._delivery", DeliveryFieldsComposer::class);

        // Password policy for every new or changed password: 10+ characters with upper
        // and lower case letters and a number. Existing passwords keep working.
        Password::defaults(fn () => Password::min(10)->letters()->mixedCase()->numbers());

        $this->registerSecurityEvents();

        // Migrations may write settings directly (DB::table): read them afresh afterwards.
        Event::listen(MigrationsEnded::class, fn () => SettingsCache::instance()->flush());
    }

    /** Logins, failed attempts, logouts and lockouts go to the audit trail. */
    private function registerSecurityEvents(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if (!$event->user instanceof User) {
                return;
            }
            $event->user->forceFill(["last_login_at" => now(), "last_login_ip" => request()->ip()])->saveQuietly();
            ActivityLog::security("login", $event->user, "Login: {$event->user->email}", $event->user);
        });

        Event::listen(Failed::class, function (Failed $event) {
            $email = mb_substr((string) ($event->credentials["email"] ?? ""), 0, 120);
            ActivityLog::security("login_failed", $event->user instanceof User ? $event->user : null, "Failed login: {$email}");
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user instanceof User) {
                ActivityLog::security("logout", $event->user, "Logout: {$event->user->email}", $event->user);
            }
        });

        Event::listen(Lockout::class, function (Lockout $event) {
            $email = mb_substr((string) $event->request->input("email"), 0, 120);
            ActivityLog::security("lockout", null, "Too many login attempts: {$email}");
        });
    }
}
