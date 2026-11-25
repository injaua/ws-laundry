<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\PickupRequestController;
use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
// Public pages run in the main store (or the one picked on the website); see PublicStore.
Route::get("/", [LandingPageController::class, "index"])->middleware("store.public")->name("home");
// Public lookup by order number: throttled so sequential numbers can't be enumerated.
Route::get("/order-status", [LandingPageController::class, "checkStatus"])
    ->middleware("throttle:10,1")
    ->name("orders.check-status");
// Public home-pickup request (website form): throttled, waits for the counter to confirm it.
Route::post("/pickup-request", [PickupRequestController::class, "store"])
    ->middleware(["throttle:5,1", "store.public"])
    ->name("pickup-requests.store");
// Customers pay by themselves (M-Pesa / e-Mola): website lookup and signed links from WhatsApp.
Route::get("/pagar", [PublicPaymentController::class, "lookupForm"])->name("pay.lookup");
Route::post("/pagar", [PublicPaymentController::class, "lookup"])->middleware("throttle:10,1")->name("pay.lookup.submit");
// Signed payment links run in the store of their order / transaction.
Route::middleware(["signed", "store.public"])->group(function () {
    Route::get("/pagar/encomenda/{order}", [PublicPaymentController::class, "show"])->name("pay.show");
    Route::post("/pagar/encomenda/{order}", [PublicPaymentController::class, "request"])->middleware("throttle:5,1")->name("pay.request");
    Route::get("/pagar/pedido/{transaction}", [PublicPaymentController::class, "statusPage"])->name("pay.status-page");
    Route::get("/pagar/pedido/{transaction}/estado", [PublicPaymentController::class, "status"])->middleware("throttle:60,1")->name("pay.status");
});
// WhatsApp Cloud API webhook (Meta): signed with the app secret, no CSRF token.
Route::get("/webhooks/whatsapp", [WhatsAppWebhookController::class, "verify"])->name("webhooks.whatsapp.verify");
Route::post("/webhooks/whatsapp", [WhatsAppWebhookController::class, "receive"])
    ->middleware("throttle:600,1")
    ->name("webhooks.whatsapp");
Route::post("/webhooks/whatsapp-web", [WhatsAppWebhookController::class, "receiveWeb"])
    ->middleware("throttle:600,1")
    ->name("webhooks.whatsapp-web");
Route::get("/language/{locale}", [LanguageController::class, "switch"])->name("language.switch");

/*
|--------------------------------------------------------------------------
| Admin Routes (auth required)
|--------------------------------------------------------------------------
*/
Route::middleware(["auth", "verified"])->group(function () {
    // Dashboard - all roles can access
    Route::get("/dashboard", [DashboardController::class, "index"])->name("dashboard");

    // Each area in its own file (abilities: App\Support\Permissions).
    require __DIR__ . "/web/sales.php";
    require __DIR__ . "/web/management.php";
    require __DIR__ . "/web/finance.php";
    require __DIR__ . "/web/documents.php";
    require __DIR__ . "/web/assistant.php";
    require __DIR__ . "/web/operations.php";
    require __DIR__ . "/web/hr.php";
    require __DIR__ . "/web/messages.php";
    require __DIR__ . "/web/account.php";
    require __DIR__ . "/web/admin.php";
});

require __DIR__ . "/auth.php";
