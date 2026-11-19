<?php

use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerPriceController;
use App\Http\Controllers\CustomerPrivacyController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PieceTrackingController;
use App\Http\Controllers\UnclaimedController;
use Illuminate\Support\Facades\Route;

/*
| Counter and records: orders, customers, payments, after-sales, piece tracking, tills, business invoices, negotiated prices, data protection.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Management routes - admin & owner only
// (registered first so /orders/trash is not shadowed by /orders/{order})
Route::middleware("can:manage-orders")->group(function () {
    Route::get("/orders/trash", [OrderController::class, "trash"])->name("orders.trash");
    Route::patch("/orders/{id}/restore", [OrderController::class, "restore"])->name("orders.restore");
    Route::resource("orders", OrderController::class)->only(["edit", "destroy"]);
    Route::resource("customers", CustomerController::class)->only(["edit", "update", "destroy"]);

    Route::patch("/payments/{payment}", [PaymentController::class, "update"])->name("payments.update");
    Route::delete("/payments/{payment}", [PaymentController::class, "destroy"])->name("payments.destroy");
});

// Operational routes - view & create: admin, owner & cashier
Route::middleware("can:work-counter")->group(function () {
    Route::resource("orders", OrderController::class)->only(["create", "store", "update"]);
    Route::resource("customers", CustomerController::class)->only(["create", "store"]);
    Route::get("/orders/{order}/payment", [OrderController::class, "payment"])->name("orders.payment");
    Route::get("/orders/{order}/label", [OrderController::class, "label"])->name("orders.label");

    // After-sales: credit notes / refunds (management issues them), complaints, unclaimed items
    Route::post("/credit-notes/{creditNote}/void", [CreditNoteController::class, "void"])->name("credit-notes.void");
    Route::get("/orders/{order}/credit-notes/create", [CreditNoteController::class, "create"])->name("orders.credit-notes.create");
    Route::post("/orders/{order}/credit-notes", [CreditNoteController::class, "store"])->name("orders.credit-notes.store");
    Route::resource("complaints", ComplaintController::class)->only(["index", "create", "store", "show", "update"]);
    Route::put("/complaints/{complaint}/attribution", [ComplaintController::class, "attribution"])->name("complaints.attribution");
    Route::post("/complaints/{complaint}/rework", [ComplaintController::class, "rework"])->name("complaints.rework");
    Route::get("/unclaimed", [UnclaimedController::class, "index"])->name("unclaimed.index");
    Route::post("/unclaimed/settings", [UnclaimedController::class, "settings"])->name("unclaimed.settings");
    Route::post("/orders/{order}/unclaimed/remind", [UnclaimedController::class, "remind"])->name("unclaimed.remind");
    Route::post("/orders/{order}/unclaimed/abandon", [UnclaimedController::class, "abandon"])->name("unclaimed.abandon");
    Route::post("/orders/{order}/unclaimed/restore", [UnclaimedController::class, "restore"])->name("unclaimed.restore");

    // Piece-by-piece tracking: scan station, tags and per-piece details
    Route::get("/tracking", [PieceTrackingController::class, "index"])->name("tracking.index");
    Route::post("/tracking/scan", [PieceTrackingController::class, "scan"])->name("tracking.scan");
    Route::get("/tracking/lookup", [PieceTrackingController::class, "lookup"])->name("tracking.lookup");
    Route::post("/orders/{order}/pieces", [PieceTrackingController::class, "store"])->name("orders.pieces.store");
    Route::get("/orders/{order}/pieces/labels", [PieceTrackingController::class, "labels"])->name("orders.pieces.labels");
    Route::put("/orders/{order}/pieces/{piece}", [PieceTrackingController::class, "update"])->scopeBindings()->name("orders.pieces.update");
    Route::delete("/orders/{order}/pieces/{piece}", [PieceTrackingController::class, "destroy"])->scopeBindings()->name("orders.pieces.destroy");

    Route::post("/payments", [PaymentController::class, "store"])->name("payments.store");

    // Till shifts (cash sessions): open, withdraw/deposit, blind close
    Route::get("/cash-sessions/current", [CashSessionController::class, "current"])->name("cash-sessions.current");
    Route::post("/cash-sessions", [CashSessionController::class, "store"])->name("cash-sessions.store");
    Route::post("/cash-sessions/{cashSession}/movements", [CashSessionController::class, "storeMovement"])->name("cash-sessions.movements.store");
    Route::get("/cash-sessions/{cashSession}/close", [CashSessionController::class, "closeForm"])->name("cash-sessions.close-form");
    Route::post("/cash-sessions/{cashSession}/close", [CashSessionController::class, "close"])->name("cash-sessions.close");
});

// Consultation: the accountant checks orders, invoices, receipts, credit notes and tills, read-only.
// (after the group above, so /orders/create and /cash-sessions/current are matched first)
Route::middleware("can:view-records")->group(function () {
    Route::resource("orders", OrderController::class)->only(["index", "show"]);
    Route::resource("customers", CustomerController::class)->only(["index", "show"]);
    Route::get("/orders/{order}/invoice", [OrderController::class, "invoice"])->name("orders.invoice");
    Route::get("/credit-notes", [CreditNoteController::class, "index"])->name("credit-notes.index");
    Route::get("/credit-notes/{creditNote}", [CreditNoteController::class, "show"])->name("credit-notes.show");
    Route::get("/cash-sessions", [CashSessionController::class, "index"])->name("cash-sessions.index");
    Route::get("/cash-sessions/{cashSession}", [CashSessionController::class, "show"])->name("cash-sessions.show");
    Route::get("/payments/{payment}/receipt", [PaymentController::class, "receipt"])->name("payments.receipt");

    // A4 documents: page, or PDF with ?format=pdf
    Route::get("/orders/{order}/document", [OrderController::class, "document"])->name("orders.document");
    Route::get("/payments/{payment}/document", [PaymentController::class, "document"])->name("payments.document");
    Route::get("/credit-notes/{creditNote}/document", [CreditNoteController::class, "document"])->name("credit-notes.document");
    Route::get("/cash-sessions/{cashSession}/document", [CashSessionController::class, "document"])->name("cash-sessions.document");
});

// Business customers: consolidated invoices, grouped payments, statements - admin, owner & accountant
Route::middleware("can:manage-finance")->group(function () {
    Route::get("/invoices", [InvoiceController::class, "index"])->name("invoices.index");
    Route::get("/invoices/create", [InvoiceController::class, "create"])->name("invoices.create");
    Route::post("/invoices", [InvoiceController::class, "store"])->name("invoices.store");
    Route::get("/invoices/{invoice}", [InvoiceController::class, "show"])->name("invoices.show");
    Route::get("/invoices/{invoice}/print", [InvoiceController::class, "print"])->name("invoices.print");
    Route::post("/invoices/{invoice}/issue", [InvoiceController::class, "issue"])->name("invoices.issue");
    Route::post("/invoices/{invoice}/void", [InvoiceController::class, "void"])->name("invoices.void");
    Route::post("/invoices/{invoice}/payments", [InvoiceController::class, "pay"])->name("invoices.pay");
    Route::delete("/invoices/{invoice}/orders/{order}", [InvoiceController::class, "removeOrder"])->name("invoices.orders.remove");

    Route::post("/customers/{customer}/payments", [CustomerAccountController::class, "pay"])->name("customers.payments.store");
    Route::get("/customers/{customer}/statement", [CustomerAccountController::class, "statement"])->name("customers.statement");
    Route::get("/payment-groups/{groupRef}/receipt", [CustomerAccountController::class, "groupReceipt"])->name("payments.group-receipt");
});

// Negotiated prices - admin & owner (commercial decisions)
Route::middleware("can:manage-store")->group(function () {
    Route::post("/customers/{customer}/prices", [CustomerPriceController::class, "store"])->name("customers.prices.store");
    Route::delete("/customers/{customer}/prices/{price}", [CustomerPriceController::class, "destroy"])->name("customers.prices.destroy");
});

// Customer data-protection requests (copy of the data, erasure) - admin & owner
Route::middleware("can:manage-store")->group(function () {
    Route::get("/customers/{customer}/privacy/export", [CustomerPrivacyController::class, "export"])->name("customers.privacy.export");
    Route::post("/customers/{customer}/privacy/anonymize", [CustomerPrivacyController::class, "anonymize"])->name("customers.privacy.anonymize");
});
