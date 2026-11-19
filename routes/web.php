<?php

use App\Http\Controllers\AbsenceJustificationController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\ActivityLogController;
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
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\CourierJobController;
use App\Http\Controllers\CourierRouteController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerMessageController;
use App\Http\Controllers\CustomerPriceController;
use App\Http\Controllers\CustomerPrivacyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryZoneController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\InternalChatController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryLotController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MachineLoadController;
use App\Http\Controllers\MaintenanceJobController;
use App\Http\Controllers\ManagementIndicatorController;
use App\Http\Controllers\MobilePaymentController;
use App\Http\Controllers\MyHrController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PeriodCloseController;
use App\Http\Controllers\PickupRequestController;
use App\Http\Controllers\PieceTrackingController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResultStatementController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\TrialBalanceController;
use App\Http\Controllers\UnclaimedController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\VatSettlementController;
use App\Http\Controllers\WalletReconciliationController;
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

    // Management routes - admin & owner only
    // (registered first so /orders/trash is not shadowed by /orders/{order})
    Route::middleware("role:admin,owner")->group(function () {
        Route::get("/orders/trash", [OrderController::class, "trash"])->name("orders.trash");
        Route::patch("/orders/{id}/restore", [OrderController::class, "restore"])->name("orders.restore");
        Route::resource("orders", OrderController::class)->only(["edit", "destroy"]);
        Route::resource("customers", CustomerController::class)->only(["edit", "update", "destroy"]);

        Route::patch("/payments/{payment}", [PaymentController::class, "update"])->name("payments.update");
        Route::delete("/payments/{payment}", [PaymentController::class, "destroy"])->name("payments.destroy");
    });

    // Operational routes - view & create: admin, owner & cashier
    Route::middleware("role:admin,owner,cashier")->group(function () {
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
    Route::middleware("role:admin,owner,cashier,accountant")->group(function () {
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

    // Reports and management indicators - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->group(function () {
        Route::get("/management", [ManagementIndicatorController::class, "index"])->name("management.index");
        Route::get("/management/export", [ManagementIndicatorController::class, "export"])->name("management.export");
        Route::get("/reports", [ReportController::class, "index"])->name("reports");
        Route::get("/reports/export", [ReportController::class, "export"])->name("reports.export");
        Route::get("/reports/profit-loss", [ReportController::class, "profitLoss"])->name("reports.profit-loss");
        Route::get("/reports/payment-methods", [ReportController::class, "paymentMethods"])->name("reports.payment-methods");
    });

    // Targets and activity logs - admin & owner only
    Route::middleware("role:admin,owner")->group(function () {
        Route::get("/management/targets", [ManagementIndicatorController::class, "targets"])->name("management.targets");
        Route::put("/management/targets", [ManagementIndicatorController::class, "saveTargets"])->name("management.targets.save");

        // Activity Logs - admin & owner only
        Route::get("/activity-logs", [ActivityLogController::class, "index"])->name("activity-logs.index");
    });

    // User management: owner can list and create cashiers, and edit/delete cashiers they created (gated in controller)
    Route::middleware("role:admin,owner")->group(function () {
        Route::get("/users", [UserController::class, "index"])->name("users.index");
        Route::get("/users/create", [UserController::class, "create"])->name("users.create");
        Route::post("/users", [UserController::class, "store"])->name("users.store");
        Route::get("/users/{user}/edit", [UserController::class, "edit"])->name("users.edit");
        Route::match(["put", "patch"], "/users/{user}", [UserController::class, "update"])->name("users.update");
        Route::delete("/users/{user}", [UserController::class, "destroy"])->name("users.destroy");
    });

    // Services & packages - admin only
    Route::middleware("role:admin")->group(function () {
        Route::post("/services/{service}/recipes", [ServiceController::class, "storeRecipe"])->name("services.recipes.store");
        Route::delete("/services/{service}/recipes/{recipe}", [ServiceController::class, "destroyRecipe"])->name("services.recipes.destroy");
        Route::post("/packages/{package}/recipes", [PackageController::class, "storeRecipe"])->name("packages.recipes.store");
        Route::delete("/packages/{package}/recipes/{recipe}", [PackageController::class, "destroyRecipe"])->name("packages.recipes.destroy");
        Route::resource("services", ServiceController::class);
        Route::resource("packages", PackageController::class);

        // User management - remaining actions admin only
        Route::resource("users", UserController::class)->except(["index", "create", "store", "edit", "update", "destroy"]);
        Route::patch("/users/{user}/toggle-active", [UserController::class, "toggleActive"])->name("users.toggle-active");
    });

    // Expenses - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->group(function () {
        Route::get("/expenses/salary-total", [ExpenseController::class, "salaryTotal"])->name("expenses.salary-total");
        Route::resource("expenses", ExpenseController::class);
        Route::resource("expense-categories", ExpenseCategoryController::class)->except(["show"]);
    });

    // Accounting - admin, owner & accountant (reopening a closed period: management only)
    Route::middleware("role:admin,owner,accountant")->prefix("accounting")->name("accounting.")->group(function () {
        Route::get("/trial-balance", [TrialBalanceController::class, "index"])->name("trial-balance");
        Route::get("/balance-sheet", [BalanceSheetController::class, "index"])->name("balance-sheet");
        Route::get("/income-statement", [ResultStatementController::class, "index"])->name("income-statement");
        Route::get("/vat-settlement", [VatSettlementController::class, "index"])->name("vat-settlement");
        Route::post("/vat-settlement", [VatSettlementController::class, "store"])->name("vat-settlement.store");
        Route::get("/period-close", [PeriodCloseController::class, "index"])->name("period-close");
        Route::post("/period-close", [PeriodCloseController::class, "close"])->name("period-close.close");
        Route::patch("/period-close/{period}/reopen", [PeriodCloseController::class, "reopen"])->middleware("role:admin,owner")->name("period-close.reopen");
        Route::get("/period-close/archive/{archive}", [PeriodCloseController::class, "show"])->name("period-close.archive");
        Route::get("/period-close/archive/{archive}/export", [PeriodCloseController::class, "export"])->name("period-close.export");
        Route::resource("accounts", AccountController::class)->except(["show"]);
        Route::resource("journal-entries", JournalEntryController::class)->except(["show"]);
    });

    // Accounts receivable - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->prefix("receivables")->name("receivables.")->group(function () {
        Route::get("/", [AccountsReceivableController::class, "index"])->name("index");
        Route::get("/export", [AccountsReceivableController::class, "export"])->name("export");
        Route::get("/customer/{customer}", [AccountsReceivableController::class, "customer"])->name("customer");
    });

    // Home pickup & delivery - counter and management
    // Mobile money: push requests (counter + courier), status, refunds, payment links
    Route::middleware("role:admin,owner,cashier,courier,accountant")->prefix("mobile-payments")->name("mobile-payments.")->group(function () {
        Route::get("/", [MobilePaymentController::class, "index"])->name("index");
        Route::get("/{transaction}", [MobilePaymentController::class, "show"])->name("show");
        Route::get("/{transaction}/status", [MobilePaymentController::class, "status"])->name("status");
    });
    Route::middleware("role:admin,owner,cashier,courier")->prefix("mobile-payments")->name("mobile-payments.")->group(function () {
        Route::post("/", [MobilePaymentController::class, "store"])->middleware("throttle:20,1")->name("store");
        Route::post("/{transaction}/refresh", [MobilePaymentController::class, "refresh"])->name("refresh");
        Route::post("/credit-notes/{creditNote}/refund", [MobilePaymentController::class, "refund"])->name("refund");
        Route::post("/orders/{order}/send-link", [MobilePaymentController::class, "sendLink"])->name("send-link");
    });

    // Wallet statements: import and reconcile - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->prefix("wallet-reconciliation")->name("wallet-reconciliation.")->group(function () {
        Route::get("/", [WalletReconciliationController::class, "index"])->name("index");
        Route::post("/", [WalletReconciliationController::class, "store"])->name("store");
        Route::get("/mapping/{token}", [WalletReconciliationController::class, "mapping"])->name("mapping");
        Route::post("/mapping/{token}", [WalletReconciliationController::class, "map"])->name("map");
        Route::get("/{statement}", [WalletReconciliationController::class, "show"])->name("show");
        Route::post("/lines/{line}/ignore", [WalletReconciliationController::class, "ignore"])->name("ignore");
    });

    // Treasury: cheques received and bank deposits (cash from the safe + cheques) - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->prefix("treasury")->name("treasury.")->group(function () {
        Route::get("/cheques", [TreasuryController::class, "cheques"])->name("cheques");
        Route::post("/cheques/clear", [TreasuryController::class, "clearMany"])->name("cheques.clear-many");
        Route::post("/cheques/{cheque}/clear", [TreasuryController::class, "clear"])->name("cheques.clear");
        Route::post("/cheques/{cheque}/bounce", [TreasuryController::class, "bounce"])->name("cheques.bounce");
        Route::get("/deposits", [TreasuryController::class, "deposits"])->name("deposits.index");
        Route::get("/deposits/create", [TreasuryController::class, "createDeposit"])->name("deposits.create");
        Route::post("/deposits", [TreasuryController::class, "storeDeposit"])->name("deposits.store");
        Route::get("/deposits/{deposit}", [TreasuryController::class, "showDeposit"])->name("deposits.show");
    });

    // Production floor: stage board, machine loads, quality control, rework, delays
    Route::middleware("role:admin,owner,cashier")->prefix("production")->name("production.")->group(function () {
        Route::get("/", [ProductionController::class, "board"])->name("board");
        Route::get("/promise", [ProductionController::class, "promise"])->name("promise");
        Route::post("/orders/{order}/advance", [ProductionController::class, "advance"])->name("advance");
        Route::post("/orders/{order}/qc", [ProductionController::class, "qc"])->name("qc");
        Route::post("/orders/{order}/rework", [ProductionController::class, "rework"])->name("rework");
        Route::post("/orders/{order}/reschedule", [ProductionController::class, "reschedule"])->name("reschedule");
        Route::post("/orders/{order}/photos", [ProductionController::class, "storePhotos"])->name("photos.store");
        Route::delete("/orders/{order}/photos/{photo}", [ProductionController::class, "destroyPhoto"])->name("photos.destroy");
        Route::put("/orders/{order}/intake", [ProductionController::class, "updateIntake"])->name("intake");
        Route::get("/loads", [MachineLoadController::class, "index"])->name("loads.index");
        Route::get("/loads/create", [MachineLoadController::class, "create"])->name("loads.create");
        Route::post("/loads", [MachineLoadController::class, "store"])->name("loads.store");
        Route::get("/loads/{load}", [MachineLoadController::class, "show"])->name("loads.show");
        Route::post("/loads/{load}/finish", [MachineLoadController::class, "finish"])->name("loads.finish");
        Route::post("/loads/{load}/cancel", [MachineLoadController::class, "cancel"])->name("loads.cancel");
        Route::get("/report", [ProductionController::class, "report"])->middleware("role:admin,owner")->name("report");
    });

    Route::middleware("role:admin,owner,cashier")->prefix("logistics")->name("logistics.")->group(function () {
        Route::get("/", [CourierJobController::class, "index"])->name("index");
        Route::get("/create", [CourierJobController::class, "create"])->name("create");
        Route::post("/", [CourierJobController::class, "store"])->name("store");
        Route::get("/route-sheet", [CourierJobController::class, "routeSheet"])->name("route-sheet");
        Route::get("/cash", [CourierJobController::class, "cash"])->name("cash");
        Route::post("/cash/{user}", [CourierJobController::class, "receiveCash"])->name("cash.receive");
        Route::get("/{courierJob}", [CourierJobController::class, "show"])->name("show");
        Route::put("/{courierJob}", [CourierJobController::class, "update"])->name("update");
        Route::post("/{courierJob}/confirm", [CourierJobController::class, "confirm"])->name("confirm");
        Route::post("/{courierJob}/cancel", [CourierJobController::class, "cancel"])->name("cancel");
    });

    // The courier's route and the actions on the road (staff may record them on the courier's behalf)
    Route::middleware("role:admin,owner,cashier,courier")->group(function () {
        Route::get("/my-route", [CourierRouteController::class, "index"])->name("courier.route");
        Route::post("/logistics/{courierJob}/start", [CourierRouteController::class, "start"])->name("logistics.start");
        Route::post("/logistics/{courierJob}/complete", [CourierRouteController::class, "complete"])->name("logistics.complete");
        Route::post("/logistics/{courierJob}/fail", [CourierRouteController::class, "fail"])->name("logistics.fail");
    });

    Route::middleware("role:admin,owner")->group(function () {
        Route::resource("delivery-zones", DeliveryZoneController::class)->except(["show"]);
    });

    // Promotions - admin & owner (commercial decisions)
    Route::middleware("role:admin,owner")->group(function () {
        Route::resource("promotions", PromotionController::class)->except(["show"]);
    });

    // Business customers: consolidated invoices, grouped payments, statements - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->group(function () {
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
    Route::middleware("role:admin,owner")->group(function () {
        Route::post("/customers/{customer}/prices", [CustomerPriceController::class, "store"])->name("customers.prices.store");
        Route::delete("/customers/{customer}/prices/{price}", [CustomerPriceController::class, "destroy"])->name("customers.prices.destroy");
    });

    // Internal chat - every role
    Route::middleware("role:admin,owner,cashier,accountant")->prefix("messages")->name("messages.")->group(function () {
        Route::get("/", [InternalChatController::class, "index"])->name("index");
        Route::post("/conversations", [InternalChatController::class, "storeConversation"])->name("conversations.store");
        Route::get("/conversations/{conversation}", [InternalChatController::class, "show"])->name("conversations.show");
        Route::post("/conversations/{conversation}/messages", [InternalChatController::class, "storeMessage"])->name("messages.store");
        Route::get("/conversations/{conversation}/poll", [InternalChatController::class, "poll"])->name("conversations.poll");
        Route::get("/unread-count", [InternalChatController::class, "unreadCount"])->name("unread");

        // Client (WhatsApp) inbox - cashier only
        Route::middleware("role:cashier")->prefix("clients")->name("clients.")->group(function () {
            Route::get("/", [CustomerMessageController::class, "index"])->name("index");
            Route::get("/unread-count", [CustomerMessageController::class, "unreadCount"])->name("unread");
            Route::post("/send-template", [CustomerMessageController::class, "sendTemplate"])->name("send-template");
            Route::get("/{phone}", [CustomerMessageController::class, "index"])->whereNumber("phone")->name("thread");
            Route::get("/{phone}/poll", [CustomerMessageController::class, "poll"])->whereNumber("phone")->name("poll");
            Route::post("/{phone}/reply", [CustomerMessageController::class, "reply"])->whereNumber("phone")->name("reply");
        });
    });

    // Resource management (equipments, stock, maintenance) - admin & owner
    Route::middleware("role:admin,owner")->prefix("resources")->name("resources.")->group(function () {
        Route::post("/maintenance/{maintenance}/parts", [MaintenanceJobController::class, "addPart"])->name("maintenance.parts.add");
        Route::delete("/maintenance/{maintenance}/parts/{part}", [MaintenanceJobController::class, "removePart"])->name("maintenance.parts.remove");
        Route::patch("/maintenance/{maintenance}/cancel", [MaintenanceJobController::class, "cancel"])->name("maintenance.cancel");
        Route::resource("maintenance", MaintenanceJobController::class);
        Route::post("/items/{item}/movements", [InventoryMovementController::class, "store"])->name("movements.store");
        Route::get("/movements/{movement}/edit", [InventoryMovementController::class, "edit"])->name("movements.edit");
        Route::put("/movements/{movement}", [InventoryMovementController::class, "update"])->name("movements.update");
        Route::delete("/movements/{movement}", [InventoryMovementController::class, "destroy"])->name("movements.destroy");
        Route::get("/items", [InventoryItemController::class, "index"])->name("items.index");
        Route::get("/items/create", [InventoryItemController::class, "create"])->name("items.create");
        Route::post("/items", [InventoryItemController::class, "store"])->name("items.store");
        Route::get("/items/{item}", [InventoryItemController::class, "show"])->name("items.show");
        Route::get("/items/{item}/edit", [InventoryItemController::class, "edit"])->name("items.edit");
        Route::put("/items/{item}", [InventoryItemController::class, "update"])->name("items.update");
        Route::patch("/items/{item}", [InventoryItemController::class, "update"]);
        Route::delete("/items/{item}", [InventoryItemController::class, "destroy"])->name("items.destroy");
        Route::resource("equipments", EquipmentController::class);
        Route::post("/equipments/{equipment}/recipes", [EquipmentController::class, "storeRecipe"])->name("equipments.recipes.store");
        Route::delete("/equipments/{equipment}/recipes/{recipe}", [EquipmentController::class, "destroyRecipe"])->name("equipments.recipes.destroy");
        Route::get("/lots", [InventoryLotController::class, "index"])->name("lots.index");
        Route::resource("suppliers", SupplierController::class)->except(["index", "show"]);
        Route::patch("/purchases/{purchase}/order", [PurchaseOrderController::class, "order"])->name("purchases.order");
        Route::patch("/purchases/{purchase}/cancel", [PurchaseOrderController::class, "cancel"])->name("purchases.cancel");
        Route::post("/purchases/{purchase}/items/{item}/receive", [PurchaseOrderController::class, "receive"])->name("purchases.receive");
        Route::resource("purchases", PurchaseOrderController::class)->except(["index", "show"]);
    });

    // Suppliers and purchase orders: consultation for the accountant (accounts payable)
    // (after the group above, so /resources/purchases/create is matched first)
    Route::middleware("role:admin,owner,accountant")->prefix("resources")->name("resources.")->group(function () {
        Route::resource("suppliers", SupplierController::class)->only(["index", "show"]);
        Route::resource("purchases", PurchaseOrderController::class)->only(["index", "show"]);
        Route::get("/purchases/{purchase}/document", [PurchaseOrderController::class, "document"])->name("purchases.document");
    });

    // HR / employee management - admin & owner
    // Payroll - admin, owner & accountant
    Route::middleware("role:admin,owner,accountant")->group(function () {
        Route::patch("/salaries/{salary}/pay", [EmployeeSalaryController::class, "pay"])->name("salaries.pay");
        Route::patch("/salaries/{salary}/unpay", [EmployeeSalaryController::class, "unpay"])->name("salaries.unpay");
        Route::get("/salaries/absence-suggestion", [EmployeeSalaryController::class, "absenceSuggestion"])->name("salaries.absence-suggestion");
        Route::get("/salaries/summary", [EmployeeSalaryController::class, "summary"])->name("salaries.summary");
        Route::get("/salaries/{salary}/payslip", [EmployeeSalaryController::class, "payslip"])->name("salaries.payslip");
        Route::resource("salaries", EmployeeSalaryController::class)->except(["show"]);
    });

    Route::middleware("role:admin,owner")->group(function () {
        Route::patch("/employees/{employee}/status", [EmployeeController::class, "updateStatus"])->name("employees.status");
        Route::patch("/employees/{employee}/terminate", [EmployeeController::class, "terminate"])->name("employees.terminate");
        Route::resource("employees", EmployeeController::class);

        Route::patch("/justifications/{justification}/review", [AbsenceJustificationController::class, "review"])->name("justifications.review");
        Route::resource("justifications", AbsenceJustificationController::class)->only(["index", "create", "store", "destroy"]);

        Route::patch("/vacations/{vacation}/review", [VacationController::class, "review"])->name("vacations.review");
        Route::get("/vacations/plan", [VacationController::class, "plan"])->name("vacations.plan");
        Route::resource("vacations", VacationController::class)->except(["show"]);

        Route::patch("/leaves/{leave}/review", [LeaveController::class, "review"])->name("leaves.review");
        Route::resource("leaves", LeaveController::class)->only(["index", "create", "store", "destroy"]);

        Route::get("/attendance", [AttendanceController::class, "index"])->name("attendance.index");
        Route::get("/attendance/summary", [AttendanceController::class, "summary"])->name("attendance.summary");
        Route::get("/attendance/daily", [AttendanceController::class, "daily"])->name("attendance.daily");
        Route::post("/attendance", [AttendanceController::class, "storeBatch"])->name("attendance.store");
        Route::delete("/attendance/{attendance}", [AttendanceController::class, "destroy"])->name("attendance.destroy");
    });

    // HR self-service - cashier views own payslips & attendance
    Route::middleware("role:cashier")->group(function () {
        Route::get("/my-payslips", [MyHrController::class, "payslips"])->name("hr.payslips");
        Route::get("/my-payslips/{salary}", [MyHrController::class, "payslip"])->name("hr.payslip");
        Route::get("/my-attendance", [MyHrController::class, "attendance"])->name("hr.attendance");
    });

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

    // Customer data-protection requests (copy of the data, erasure) - admin & owner
    Route::middleware("role:admin,owner")->group(function () {
        Route::get("/customers/{customer}/privacy/export", [CustomerPrivacyController::class, "export"])->name("customers.privacy.export");
        Route::post("/customers/{customer}/privacy/anonymize", [CustomerPrivacyController::class, "anonymize"])->name("customers.privacy.anonymize");
    });

    // Stores (locations) of the company - admin only
    Route::middleware("role:admin")->prefix("admin/stores")->name("admin.stores.")->group(function () {
        Route::get("/", [StoreController::class, "index"])->name("index");
        Route::get("/overview", [StoreController::class, "overview"])->name("overview");
        Route::get("/create", [StoreController::class, "create"])->name("create");
        Route::post("/", [StoreController::class, "store"])->name("store");
        Route::get("/{store}/edit", [StoreController::class, "edit"])->name("edit");
        Route::put("/{store}", [StoreController::class, "update"])->name("update");
        Route::post("/{store}/switch", [StoreController::class, "switch"])->name("switch");
    });

    // Security & compliance - admin only
    Route::middleware("role:admin")->prefix("admin/security")->name("admin.security.")->group(function () {
        Route::get("/", [SecurityController::class, "index"])->name("index");
        Route::put("/settings", [SecurityController::class, "updateSettings"])->name("settings");
        Route::post("/backups", [SecurityController::class, "runBackup"])->middleware("throttle:5,1")->name("backups.run");
        Route::get("/backups/{name}", [SecurityController::class, "downloadBackup"])->middleware("password.confirm")->name("backups.download");
        Route::post("/users/{user}/two-factor/reset", [SecurityController::class, "resetTwoFactor"])->name("users.two-factor.reset");
        Route::post("/users/{user}/sessions/revoke", [SecurityController::class, "revokeSessions"])->name("users.sessions.revoke");
    });

    // Landing page management - admin & owner
    Route::middleware("role:admin,owner")->prefix("admin")->name("admin.")->group(function () {
        // Company-wide (every store): public website, localization, languages, currencies,
        // the WhatsApp channel, testimonials and gallery - admin only.
        Route::middleware("role:admin")->group(function () {
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
            Route::post("/notifications/channels", [NotificationSettingController::class, "updateChannels"])->name("notifications.channels");
            Route::post("/notifications/channels/release", [NotificationSettingController::class, "releaseChannels"])->name("notifications.channels.release");
            Route::get("/notifications/qr-status", [NotificationSettingController::class, "webStatus"])->name("notifications.web-status");
            Route::post("/notifications/qr-logout", [NotificationSettingController::class, "webLogout"])->name("notifications.web-logout");
            Route::post("/notifications/qr-start", [NotificationSettingController::class, "webStart"])->name("notifications.web-start");
            Route::resource("testimonials", TestimonialController::class);
            Route::resource("galleries", GalleryController::class);
        });

        Route::get("/mobile-money", [MobileMoneySettingController::class, "edit"])->middleware("role:admin")->name("mobile-money.edit");
        Route::put("/mobile-money/methods", [MobileMoneySettingController::class, "updateMethods"])->middleware("role:admin")->name("mobile-money.methods");
        Route::post("/mobile-money/release", [MobileMoneySettingController::class, "release"])->middleware("role:admin")->name("mobile-money.release");
        Route::put("/mobile-money/{provider}", [MobileMoneySettingController::class, "update"])->middleware("role:admin")->name("mobile-money.update");
        // System settings: identity & branding of the store worked in (falls back to the company values)
        Route::get("/system-settings/general", [SystemSettingController::class, "general"])->name("system-settings.general");
        Route::post("/system-settings/general", [SystemSettingController::class, "updateGeneral"])->name("system-settings.general.update");

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
});

require __DIR__ . "/auth.php";
