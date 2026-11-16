<?php

use App\Http\Controllers\AbsenceJustificationController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\BusinessSettingController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\LandingSettingController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BalancoController;
use App\Http\Controllers\BalanceteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MaintenanceJobController;
use App\Http\Controllers\MyHrController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\IvaSettlementController;
use App\Http\Controllers\ResultStatementController;
use App\Http\Controllers\VacationController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::get("/", [LandingPageController::class, "index"])->name("home");
Route::get("/order-status", [LandingPageController::class, "checkStatus"])->name("orders.check-status");
Route::get("/language/{locale}", [App\Http\Controllers\LanguageController::class, "switch"])->name("language.switch");

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
        Route::resource("orders", OrderController::class)->only(["index", "create", "store", "show", "update"]);
        Route::resource("customers", CustomerController::class)->only(["index", "create", "store", "show"]);
        Route::get("/orders/{order}/payment", [OrderController::class, "payment"])->name("orders.payment");
        Route::get("/orders/{order}/invoice", [OrderController::class, "invoice"])->name("orders.invoice");
        Route::get("/orders/{order}/label", [OrderController::class, "label"])->name("orders.label");

        Route::post("/payments", [PaymentController::class, "store"])->name("payments.store");
        Route::get("/payments/{payment}/receipt", [PaymentController::class, "receipt"])->name("payments.receipt");
    });

    // Reports - admin & owner
    Route::middleware("role:admin,owner")->group(function () {
        Route::get("/reports", [ReportController::class, "index"])->name("reports");
        Route::get("/reports/export", [ReportController::class, "export"])->name("reports.export");
Route::get("/reports/profit-loss", [ReportController::class, "profitLoss"])->name("reports.profit-loss");
Route::get("/reports/payment-methods", [ReportController::class, "paymentMethods"])->name("reports.payment-methods");

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
        Route::resource("services", ServiceController::class);
        Route::resource("packages", PackageController::class);

    // User management - remaining actions admin only
    Route::resource("users", UserController::class)->except(["index", "create", "store", "edit", "update", "destroy"]);
    Route::patch("/users/{user}/toggle-active", [UserController::class, "toggleActive"])->name("users.toggle-active");
    });

    // Expenses - admin & owner
    Route::middleware("role:admin,owner")->group(function () {
        Route::get("/expenses/salary-total", [ExpenseController::class, "salaryTotal"])->name("expenses.salary-total");
        Route::resource("expenses", ExpenseController::class);
    });

    // Accounting - admin & owner
    Route::middleware("role:admin,owner")->prefix("accounting")->name("accounting.")->group(function () {
        Route::get("/balancete", [BalanceteController::class, "index"])->name("balancete");
        Route::get("/balanco", [BalancoController::class, "index"])->name("balanco");
        Route::get("/demonstracao-resultados", [ResultStatementController::class, "index"])->name("demonstracao-resultados");
        Route::get("/iva-acerto", [IvaSettlementController::class, "index"])->name("iva-acerto");
        Route::post("/iva-acerto", [IvaSettlementController::class, "store"])->name("iva-acerto.settlement");
        Route::resource("accounts", AccountController::class)->except(["show"]);
        Route::resource("journal-entries", JournalEntryController::class)->except(["show"]);
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
    });

    // HR / employee management - admin & owner
    Route::middleware("role:admin,owner")->group(function () {
        Route::resource("expense-categories", ExpenseCategoryController::class)->except(["show"]);
        Route::patch("/employees/{employee}/status", [EmployeeController::class, "updateStatus"])->name("employees.status");
        Route::patch("/employees/{employee}/terminate", [EmployeeController::class, "terminate"])->name("employees.terminate");
        Route::resource("employees", EmployeeController::class);

        Route::patch("/salaries/{salary}/pay", [EmployeeSalaryController::class, "pay"])->name("salaries.pay");
        Route::patch("/salaries/{salary}/unpay", [EmployeeSalaryController::class, "unpay"])->name("salaries.unpay");
        Route::get("/salaries/absence-suggestion", [EmployeeSalaryController::class, "absenceSuggestion"])->name("salaries.absence-suggestion");
        Route::get("/salaries/summary", [EmployeeSalaryController::class, "summary"])->name("salaries.summary");
        Route::resource("salaries", EmployeeSalaryController::class)->except(["show"]);

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
        Route::get("/my-attendance", [MyHrController::class, "attendance"])->name("hr.attendance");
    });

    Route::get("/profile", [ProfileController::class, "edit"])->name("profile.edit");
    Route::patch("/profile", [ProfileController::class, "update"])->name("profile.update");
    Route::delete("/profile", [ProfileController::class, "destroy"])->name("profile.destroy");

    // Landing page management - admin & owner
    Route::middleware("role:admin,owner")->prefix("admin")->name("admin.")->group(function () {
        Route::get("/landing-settings", [LandingSettingController::class, "index"])
            ->name("landing-settings.index");
        Route::post("/landing-settings", [LandingSettingController::class, "update"])
            ->name("landing-settings.update");

        Route::get("/business-settings", [BusinessSettingController::class, "index"])
            ->name("business-settings.index");
        Route::post("/business-settings", [BusinessSettingController::class, "update"])
            ->name("business-settings.update");

        Route::resource("testimonials", TestimonialController::class);
        Route::resource("galleries", GalleryController::class);
    });
});

require __DIR__ . "/auth.php";
