<?php

use App\Http\Controllers\Admin\BusinessSettingController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\LandingSettingController;
use App\Http\Controllers\Admin\MobileMoneySettingController;
use App\Http\Controllers\Admin\NotificationSettingController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\SystemSettingController;
use App\Http\Controllers\Admin\TestimonialController;
use Illuminate\Support\Facades\Route;

/*
| Company administration (stores, security, system settings, public website) and the store's settings.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Stores (locations) of the company - admin only
Route::middleware("can:administer-company")->prefix("admin/stores")->name("admin.stores.")->group(function () {
    Route::get("/", [StoreController::class, "index"])->name("index");
    Route::get("/overview", [StoreController::class, "overview"])->name("overview");
    Route::get("/create", [StoreController::class, "create"])->name("create");
    Route::post("/", [StoreController::class, "store"])->name("store");
    Route::get("/{store}/edit", [StoreController::class, "edit"])->name("edit");
    Route::put("/{store}", [StoreController::class, "update"])->name("update");
    Route::post("/{store}/switch", [StoreController::class, "switch"])->name("switch");
});

// Security & compliance - admin only
Route::middleware("can:administer-company")->prefix("admin/security")->name("admin.security.")->group(function () {
    Route::get("/", [SecurityController::class, "index"])->name("index");
    Route::put("/settings", [SecurityController::class, "updateSettings"])->name("settings");
    Route::post("/backups", [SecurityController::class, "runBackup"])->middleware("throttle:5,1")->name("backups.run");
    Route::get("/backups/{name}", [SecurityController::class, "downloadBackup"])->middleware("password.confirm")->name("backups.download");
    Route::post("/users/{user}/two-factor/reset", [SecurityController::class, "resetTwoFactor"])->name("users.two-factor.reset");
    Route::post("/users/{user}/sessions/revoke", [SecurityController::class, "revokeSessions"])->name("users.sessions.revoke");
});

// Landing page management - admin & owner
Route::middleware("can:configure-store")->prefix("admin")->name("admin.")->group(function () {
    // Company-wide (every store): public website, localization, languages, currencies,
    // the WhatsApp channel, testimonials and gallery - admin only.
    Route::middleware("can:administer-company")->group(function () {
        Route::get("/landing-settings", [LandingSettingController::class, "index"])->name("landing-settings.index");
        Route::post("/landing-settings", [LandingSettingController::class, "update"])->name("landing-settings.update");
        Route::get("/system-settings/localization", [SystemSettingController::class, "localization"])->name("system-settings.localization");
        Route::post("/system-settings/localization", [SystemSettingController::class, "updateLocalization"])->name("system-settings.localization.update");
        Route::resource("system-settings/languages", App\Http\Controllers\Admin\LanguageController::class)->except(["show"])->names("languages");
        Route::get("/system-settings/languages/{language}/translations", [App\Http\Controllers\Admin\LanguageController::class, "translations"])->name("languages.translations");
        Route::post("/system-settings/languages/{language}/translations", [App\Http\Controllers\Admin\LanguageController::class, "saveTranslations"])->name("languages.translations.save");
        Route::get("/system-settings/languages/{language}/export", [App\Http\Controllers\Admin\LanguageController::class, "export"])->name("languages.export");
        Route::post("/system-settings/languages/{language}/import", [App\Http\Controllers\Admin\LanguageController::class, "import"])->name("languages.import");
        Route::resource("system-settings/currencies", CurrencyController::class)->except(["show"])->names("currencies");
        Route::post("/system-settings/currencies/{currency}/default", [CurrencyController::class, "makeDefault"])->name("currencies.default");
        // A store going back to the company payments / WhatsApp configuration: the admin decides.
        Route::post("/notifications/channels/release", [NotificationSettingController::class, "releaseChannels"])->name("notifications.channels.release");
        Route::post("/mobile-money/release", [MobileMoneySettingController::class, "release"])->name("mobile-money.release");
        Route::resource("testimonials", TestimonialController::class);
        Route::resource("galleries", GalleryController::class);

        // System settings (identity & branding) - admin only, not part of a store's settings
        Route::get("/system-settings/general", [SystemSettingController::class, "general"])->name("system-settings.general");
        Route::post("/system-settings/general", [SystemSettingController::class, "updateGeneral"])->name("system-settings.general.update");
    });

    // Payments and WhatsApp: the admin edits the company's or a store's; the owner configures
    // their own store (ChoosesSettingsLevel).
    Route::get("/mobile-money", [MobileMoneySettingController::class, "edit"])->name("mobile-money.edit");
    Route::put("/mobile-money/methods", [MobileMoneySettingController::class, "updateMethods"])->name("mobile-money.methods");
    Route::put("/mobile-money/{provider}", [MobileMoneySettingController::class, "update"])->name("mobile-money.update");
    Route::post("/notifications/channels", [NotificationSettingController::class, "updateChannels"])->name("notifications.channels");
    Route::get("/notifications/qr-status", [NotificationSettingController::class, "webStatus"])->name("notifications.web-status");
    Route::post("/notifications/qr-logout", [NotificationSettingController::class, "webLogout"])->name("notifications.web-logout");
    Route::post("/notifications/qr-start", [NotificationSettingController::class, "webStart"])->name("notifications.web-start");

    Route::get("/business-settings", [BusinessSettingController::class, "index"])
        ->name("business-settings.index");
    Route::post("/business-settings", [BusinessSettingController::class, "update"])
        ->name("business-settings.update");

    // Automatic WhatsApp notifications and message templates
    Route::get("/notifications", [NotificationSettingController::class, "index"])->name("notifications.index");
    Route::post("/notifications", [NotificationSettingController::class, "update"])->name("notifications.update");
    Route::post("/notifications/templates", [NotificationSettingController::class, "storeTemplate"])->name("notifications.templates.store");
    Route::put("/notifications/templates/{template}", [NotificationSettingController::class, "updateTemplate"])->name("notifications.templates.update");
});
