<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountsReceivableController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\MobilePaymentController;
use App\Http\Controllers\PeriodCloseController;
use App\Http\Controllers\ResultStatementController;
use App\Http\Controllers\TreasuryController;
use App\Http\Controllers\TrialBalanceController;
use App\Http\Controllers\VatSettlementController;
use App\Http\Controllers\WalletReconciliationController;
use Illuminate\Support\Facades\Route;

/*
| Finance: expenses, accounting, receivables, mobile money, wallet reconciliation, treasury, payroll.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

// Expenses - admin, owner & accountant
Route::middleware("can:manage-finance")->group(function () {
    Route::get("/expenses/salary-total", [ExpenseController::class, "salaryTotal"])->name("expenses.salary-total");
    Route::resource("expenses", ExpenseController::class);
    Route::resource("expense-categories", ExpenseCategoryController::class)->except(["show"]);
});

// Accounting - admin, owner & accountant (reopening a closed period: management only)
Route::middleware("can:manage-finance")->prefix("accounting")->name("accounting.")->group(function () {
    Route::get("/trial-balance", [TrialBalanceController::class, "index"])->name("trial-balance");
    Route::get("/balance-sheet", [BalanceSheetController::class, "index"])->name("balance-sheet");
    Route::get("/income-statement", [ResultStatementController::class, "index"])->name("income-statement");
    Route::get("/vat-settlement", [VatSettlementController::class, "index"])->name("vat-settlement");
    Route::post("/vat-settlement", [VatSettlementController::class, "store"])->name("vat-settlement.store");
    Route::get("/period-close", [PeriodCloseController::class, "index"])->name("period-close");
    Route::post("/period-close", [PeriodCloseController::class, "close"])->name("period-close.close");
    Route::patch("/period-close/{period}/reopen", [PeriodCloseController::class, "reopen"])->middleware("can:manage-store")->name("period-close.reopen");
    Route::get("/period-close/archive/{archive}", [PeriodCloseController::class, "show"])->name("period-close.archive");
    Route::get("/period-close/archive/{archive}/export", [PeriodCloseController::class, "export"])->name("period-close.export");
    Route::resource("accounts", AccountController::class)->except(["show"]);
    Route::resource("journal-entries", JournalEntryController::class)->except(["show"]);
});

// Accounts receivable - admin, owner & accountant
Route::middleware("can:manage-finance")->prefix("receivables")->name("receivables.")->group(function () {
    Route::get("/", [AccountsReceivableController::class, "index"])->name("index");
    Route::get("/export", [AccountsReceivableController::class, "export"])->name("export");
    Route::get("/customer/{customer}", [AccountsReceivableController::class, "customer"])->name("customer");
});

// Home pickup & delivery - counter and management
// Mobile money: push requests (counter + courier), status, refunds, payment links
Route::middleware("can:view-mobile-payments")->prefix("mobile-payments")->name("mobile-payments.")->group(function () {
    Route::get("/", [MobilePaymentController::class, "index"])->name("index");
    Route::get("/{transaction}", [MobilePaymentController::class, "show"])->name("show");
    Route::get("/{transaction}/status", [MobilePaymentController::class, "status"])->name("status");
});
Route::middleware("can:charge-mobile-payments")->prefix("mobile-payments")->name("mobile-payments.")->group(function () {
    Route::post("/", [MobilePaymentController::class, "store"])->middleware("throttle:20,1")->name("store");
    Route::post("/{transaction}/refresh", [MobilePaymentController::class, "refresh"])->name("refresh");
    Route::post("/credit-notes/{creditNote}/refund", [MobilePaymentController::class, "refund"])->name("refund");
    Route::post("/orders/{order}/send-link", [MobilePaymentController::class, "sendLink"])->name("send-link");
});

// Wallet statements: import and reconcile - admin, owner & accountant
Route::middleware("can:manage-finance")->prefix("wallet-reconciliation")->name("wallet-reconciliation.")->group(function () {
    Route::get("/", [WalletReconciliationController::class, "index"])->name("index");
    Route::post("/", [WalletReconciliationController::class, "store"])->name("store");
    Route::get("/mapping/{token}", [WalletReconciliationController::class, "mapping"])->name("mapping");
    Route::post("/mapping/{token}", [WalletReconciliationController::class, "map"])->name("map");
    Route::get("/{statement}", [WalletReconciliationController::class, "show"])->name("show");
    Route::post("/lines/{line}/ignore", [WalletReconciliationController::class, "ignore"])->name("ignore");
});

// Treasury: cheques received and bank deposits (cash from the safe + cheques) - admin, owner & accountant
Route::middleware("can:manage-finance")->prefix("treasury")->name("treasury.")->group(function () {
    Route::get("/cheques", [TreasuryController::class, "cheques"])->name("cheques");
    Route::post("/cheques/clear", [TreasuryController::class, "clearMany"])->name("cheques.clear-many");
    Route::post("/cheques/{cheque}/clear", [TreasuryController::class, "clear"])->name("cheques.clear");
    Route::post("/cheques/{cheque}/bounce", [TreasuryController::class, "bounce"])->name("cheques.bounce");
    Route::get("/deposits", [TreasuryController::class, "deposits"])->name("deposits.index");
    Route::get("/deposits/create", [TreasuryController::class, "createDeposit"])->name("deposits.create");
    Route::post("/deposits", [TreasuryController::class, "storeDeposit"])->name("deposits.store");
    Route::get("/deposits/{deposit}", [TreasuryController::class, "showDeposit"])->name("deposits.show");
});

// HR / employee management - admin & owner
// Payroll - admin, owner & accountant
Route::middleware("can:manage-finance")->group(function () {
    Route::patch("/salaries/{salary}/pay", [EmployeeSalaryController::class, "pay"])->name("salaries.pay");
    Route::patch("/salaries/{salary}/unpay", [EmployeeSalaryController::class, "unpay"])->name("salaries.unpay");
    Route::get("/salaries/absence-suggestion", [EmployeeSalaryController::class, "absenceSuggestion"])->name("salaries.absence-suggestion");
    Route::get("/salaries/summary", [EmployeeSalaryController::class, "summary"])->name("salaries.summary");
    Route::get("/salaries/{salary}/payslip", [EmployeeSalaryController::class, "payslip"])->name("salaries.payslip");
    Route::resource("salaries", EmployeeSalaryController::class)->except(["show"]);
});
