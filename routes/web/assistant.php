<?php

use App\Http\Controllers\Admin\AiSettingController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\InsightController;
use Illuminate\Support\Facades\Route;

/*
| AI: the assistant (every role, with the tools of the role), the automatic insights (admin
| and owner) and the AI provider configuration (admin: company or store; owner: their store).
| Loaded inside the auth group of routes/web.php. Abilities (can:…): App\Support\Permissions.
*/

Route::middleware("can:use-assistant")->prefix("assistant")->name("assistant.")->group(function () {
    Route::get("/", [AssistantController::class, "index"])->name("index");
    Route::post("/ask", [AssistantController::class, "ask"])->middleware("throttle:20,1")->name("ask");
    Route::delete("/{conversation}", [AssistantController::class, "destroy"])->name("destroy");
});

Route::middleware("can:receive-insights")->prefix("insights")->name("insights.")->group(function () {
    Route::get("/", [InsightController::class, "index"])->name("index");
    Route::post("/toggle", [InsightController::class, "toggle"])->name("toggle");
    Route::post("/generate", [InsightController::class, "generate"])->middleware("throttle:5,1")->name("generate");
    Route::post("/{insight}/status", [InsightController::class, "status"])->name("status");
    Route::post("/{insight}/feedback", [InsightController::class, "feedback"])->name("feedback");
});

Route::middleware("can:configure-ai")->prefix("admin/ai")->name("admin.ai.")->group(function () {
    Route::get("/", [AiSettingController::class, "edit"])->name("edit");
    Route::put("/", [AiSettingController::class, "update"])->name("update");
    Route::post("/models", [AiSettingController::class, "models"])->middleware("throttle:20,1")->name("models");
    Route::post("/test", [AiSettingController::class, "test"])->middleware("throttle:10,1")->name("test");
    Route::post("/release", [AiSettingController::class, "release"])->middleware("can:administer-company")->name("release");
});
