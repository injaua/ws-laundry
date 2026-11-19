<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DeliveryZoneController;
use App\Http\Controllers\ManagementIndicatorController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| Management: indicators, reports, targets, activity log, users, catalogue (services & packages), delivery zones, promotions.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Reports and management indicators - admin, owner & accountant
Route::middleware("can:view-reports")->group(function () {
    Route::get("/management", [ManagementIndicatorController::class, "index"])->name("management.index");
    Route::get("/management/export", [ManagementIndicatorController::class, "export"])->name("management.export");
    Route::get("/reports", [ReportController::class, "index"])->name("reports");
    Route::get("/reports/export", [ReportController::class, "export"])->name("reports.export");
    Route::get("/reports/profit-loss", [ReportController::class, "profitLoss"])->name("reports.profit-loss");
    Route::get("/reports/payment-methods", [ReportController::class, "paymentMethods"])->name("reports.payment-methods");
});

// Targets and activity logs - admin & owner only
Route::middleware("can:manage-store")->group(function () {
    Route::get("/management/targets", [ManagementIndicatorController::class, "targets"])->name("management.targets");
    Route::put("/management/targets", [ManagementIndicatorController::class, "saveTargets"])->name("management.targets.save");

    // Activity Logs - admin & owner only
    Route::get("/activity-logs", [ActivityLogController::class, "index"])->name("activity-logs.index");
});

// User management: owner can list and create cashiers, and edit/delete cashiers they created (gated in controller)
Route::middleware("can:manage-users")->group(function () {
    Route::get("/users", [UserController::class, "index"])->name("users.index");
    Route::get("/users/create", [UserController::class, "create"])->name("users.create");
    Route::post("/users", [UserController::class, "store"])->name("users.store");
    Route::get("/users/{user}/edit", [UserController::class, "edit"])->name("users.edit");
    Route::match(["put", "patch"], "/users/{user}", [UserController::class, "update"])->name("users.update");
    Route::delete("/users/{user}", [UserController::class, "destroy"])->name("users.destroy");
});

// Services & packages of the store worked in - admin & owner (the catalogue is per store)
Route::middleware("can:manage-catalog")->group(function () {
    Route::post("/services/{service}/recipes", [ServiceController::class, "storeRecipe"])->name("services.recipes.store");
    Route::delete("/services/{service}/recipes/{recipe}", [ServiceController::class, "destroyRecipe"])->name("services.recipes.destroy");
    Route::post("/packages/{package}/recipes", [PackageController::class, "storeRecipe"])->name("packages.recipes.store");
    Route::delete("/packages/{package}/recipes/{recipe}", [PackageController::class, "destroyRecipe"])->name("packages.recipes.destroy");
    Route::resource("services", ServiceController::class);
    Route::resource("packages", PackageController::class);
});

Route::middleware("can:administer-company")->group(function () {
    // User management - remaining actions admin only
    Route::resource("users", UserController::class)->except(["index", "create", "store", "edit", "update", "destroy"]);
    Route::patch("/users/{user}/toggle-active", [UserController::class, "toggleActive"])->name("users.toggle-active");
});

Route::middleware("can:manage-store")->group(function () {
    Route::resource("delivery-zones", DeliveryZoneController::class)->except(["show"]);
});

// Promotions - admin & owner (commercial decisions)
Route::middleware("can:manage-store")->group(function () {
    Route::resource("promotions", PromotionController::class)->except(["show"]);
});
