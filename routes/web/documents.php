<?php

use App\Http\Controllers\DocumentCategoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentExchangeController;
use App\Http\Controllers\DocumentFolderController;
use Illuminate\Support\Facades\Route;

/*
| Document archive: Word, Excel, PowerPoint and PDF files in folders and categories.
| Loaded inside the auth group of routes/web.php. Abilities (can:…): App\Support\Permissions.
*/

// Admin, owner & accountant (per-document changes: DocumentPolicy)
Route::middleware("can:manage-documents")->prefix("document-archive")->name("documents-archive.")->group(function () {
    Route::get("/", [DocumentController::class, "index"])->name("index");
    Route::post("/", [DocumentController::class, "store"])->middleware("throttle:30,1")->name("store");

    // Exchange between admin, owner and accountant (received / sent: index ?view=received|sent)
    Route::post("/send", [DocumentExchangeController::class, "send"])->middleware("throttle:30,1")->name("send");
    Route::post("/shares/{share}/read", [DocumentExchangeController::class, "read"])->name("shares.read");
    Route::delete("/shares/{share}", [DocumentExchangeController::class, "destroy"])->name("shares.destroy");
    Route::post("/{document}/send", [DocumentExchangeController::class, "forward"])->middleware("throttle:30,1")->name("forward");
    Route::get("/{document}/edit", [DocumentController::class, "edit"])->name("edit");
    Route::put("/{document}", [DocumentController::class, "update"])->name("update");
    Route::delete("/{document}", [DocumentController::class, "destroy"])->name("destroy");
    Route::get("/{document}/download", [DocumentController::class, "download"])->name("download");
    Route::get("/{document}/preview", [DocumentController::class, "preview"])->name("preview");
    Route::post("/{document}/favorite", [DocumentController::class, "favorite"])->name("favorite");

    Route::post("/folders", [DocumentFolderController::class, "store"])->name("folders.store");
    Route::put("/folders/{folder}", [DocumentFolderController::class, "update"])->name("folders.update");
    Route::delete("/folders/{folder}", [DocumentFolderController::class, "destroy"])->name("folders.destroy");

    Route::post("/categories", [DocumentCategoryController::class, "store"])->name("categories.store");
    Route::put("/categories/{category}", [DocumentCategoryController::class, "update"])->name("categories.update");
    Route::delete("/categories/{category}", [DocumentCategoryController::class, "destroy"])->name("categories.destroy");
});
