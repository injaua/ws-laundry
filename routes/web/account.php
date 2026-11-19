<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Help, profile and the user's own account security.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Help: the manual topics for the signed-in user role (every role)
Route::get("/help", [HelpController::class, "index"])->name("help.index");
Route::get("/help/{topic}", [HelpController::class, "show"])->where("topic", "[a-z-]+")->name("help.show");

Route::get("/profile", [ProfileController::class, "edit"])->name("profile.edit");
Route::patch("/profile", [ProfileController::class, "update"])->name("profile.update");
Route::delete("/profile", [ProfileController::class, "destroy"])->name("profile.destroy");

// Own account security: two-factor authentication and active sessions
Route::get("/profile/security", [TwoFactorController::class, "show"])->name("profile.security");
Route::middleware("throttle:10,1")->prefix("two-factor")->name("two-factor.")->group(function () {
    Route::post("/enable", [TwoFactorController::class, "enable"])->name("enable");
    Route::post("/confirm", [TwoFactorController::class, "confirm"])->name("confirm");
    Route::post("/cancel", [TwoFactorController::class, "cancel"])->name("cancel");
    Route::post("/disable", [TwoFactorController::class, "disable"])->name("disable");
    Route::post("/recovery-codes", [TwoFactorController::class, "regenerateCodes"])->name("recovery-codes");
    Route::post("/sessions/revoke", [TwoFactorController::class, "revokeSessions"])->name("sessions.revoke");
});
