<?php

use App\Http\Controllers\AbsenceJustificationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MyHrController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

/*
| HR: employees, absence justifications, vacations, leave, attendance, and the cashier's self-service.
| Loaded inside the auth group of routes/web.php; the order of the files matters only within a file.
| Abilities (can:…): App\Support\Permissions.
*/

Route::middleware("can:manage-hr")->group(function () {
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
Route::middleware("can:hr-self-service")->group(function () {
    Route::get("/my-payslips", [MyHrController::class, "payslips"])->name("hr.payslips");
    Route::get("/my-payslips/{salary}", [MyHrController::class, "payslip"])->name("hr.payslip");
    Route::get("/my-attendance", [MyHrController::class, "attendance"])->name("hr.attendance");
});
