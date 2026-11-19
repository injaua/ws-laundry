<?php

use App\Http\Controllers\CustomerMessageController;
use App\Http\Controllers\InternalChatController;
use Illuminate\Support\Facades\Route;

/*
| Internal chat and the WhatsApp inbox with customers.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Internal chat - every role
Route::middleware("can:use-chat")->prefix("messages")->name("messages.")->group(function () {
    Route::get("/", [InternalChatController::class, "index"])->name("index");
    Route::post("/conversations", [InternalChatController::class, "storeConversation"])->name("conversations.store");
    Route::get("/conversations/{conversation}", [InternalChatController::class, "show"])->name("conversations.show");
    Route::post("/conversations/{conversation}/messages", [InternalChatController::class, "storeMessage"])->name("messages.store");
    Route::get("/conversations/{conversation}/poll", [InternalChatController::class, "poll"])->name("conversations.poll");
    Route::get("/unread-count", [InternalChatController::class, "unreadCount"])->name("unread");

    // Client (WhatsApp) inbox - cashier only
    Route::middleware("can:answer-customers")->prefix("clients")->name("clients.")->group(function () {
        Route::get("/", [CustomerMessageController::class, "index"])->name("index");
        Route::get("/unread-count", [CustomerMessageController::class, "unreadCount"])->name("unread");
        Route::post("/send-template", [CustomerMessageController::class, "sendTemplate"])->name("send-template");
        Route::get("/{phone}", [CustomerMessageController::class, "index"])->whereNumber("phone")->name("thread");
        Route::get("/{phone}/poll", [CustomerMessageController::class, "poll"])->whereNumber("phone")->name("poll");
        Route::post("/{phone}/reply", [CustomerMessageController::class, "reply"])->whereNumber("phone")->name("reply");
    });
});
